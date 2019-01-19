<?php
function initializeDatabase() {
  shell_exec("mysql -h cse-cmpsc431 -u username -ppassword < import.sql");
}

function openDatabase() {
  include 'constants.php';
  if ($connection = mysql_connect($HOST, $USER, $PASS)) {
    if (!mysql_select_db ($DB)){
      throw new Exception('MySQL error #' . mysql_errno() . ": " . mysql_error());
    }
  } else {
    throw new Exception('MySQL error #' . mysql_errno() . ": " . mysql_error());
  }
}

function begin(){
    mysql_query("BEGIN");
}

function commit(){
    mysql_query("COMMIT");
}

function rollback(){
    mysql_query("ROLLBACK");
}

function closeDatabase(){
  mysql_close();
}

function db_read($statement, $args, $zero_ok=true, $output_format="cell") {
  array_unshift($args, $statement);
  $query = call_user_func_array('sprintf', $args);
  if (!($result = mysql_query($query))) {
    throw new Exception('MySQL error #' . mysql_errno() . ": " . mysql_error());
  } else if ((mysql_affected_rows() == 0) && (!$zero_ok)) {
    throw new Exception('Zero rows affected by command');
  } else {
    if (mysql_num_rows($result) == 0) {
      return null;
    } else if ($output_format == "all") {
      $results = array();
      while($row = mysql_fetch_assoc($result)) {
        array_push($results, $row);
      }
      return $results;
    } else {
      $row = mysql_fetch_array($result);
      return $output_format == "cell" ? $row[0] : $row;
    }
  }
}

function db_write($statement, $args) {
  array_unshift($args, $statement);
  $query = call_user_func_array('sprintf', $args);
  if (!($result = mysql_query($query))) {
    throw new Exception('Unable to execute write: ' . $result);
  }
  return true;
}

function employee_is_schedule_on_date($employee_id, $date) {
  include 'constants.php';
  $row = db_read($STMT_FIND_EMPLOYEE_SCHEDULES, array($employee_id, $date), 'row');
  return $row != null;
}

function employee_is_dayoff_on_date($employee_id, $date) {
  include 'constants.php';
  $row = db_read($STMT_FIND_EMPLOYEE_DAY_OFF, array($employee_id, $date), 'row');
  return $row != null;
}

function scheduleOne($employee_id, $date, $department, $shift_from, $shift_length) {
  include 'constants.php';
  try {
    begin();
    if (employee_is_dayoff_on_date($employee_id, $date))
      throw new Exception("Employee is already day off on that day");
    $row = db_read($STMT_FIND_EMPLOYEE_INFO, array($employee_id), true, 'row');
    $employee_type_id = $row[3];
    $department_id = db_read($STMT_FIND_DEPARTMENT_ID, array($department));
    $shift_id = db_read($STMT_FIND_SHIFT_ID, array($shift_from, $shift_length));
    db_write($STMT_ADD_SCHEDULE, array($date, $employee_id, $department_id, $shift_id));
    db_write($STMT_DECREMENT_NEED, array($department_id, $employee_type_id, $shift_id));
  } catch (Exception $e) {
    rollback();
    printf("ERROR: " . $e->getMessage() . "\n");
    return false;
  }
  commit();
  return true;
}

function unscheduleOne($employee_id, $date) {
  include 'constants.php';
  try {
    begin();
    if (!employee_is_schedule_on_date($employee_id, $date))
      throw new Exception('Employee is not scheduled on that day');
    $row = db_read($STMT_FIND_EMPLOYEE_INFO, array($employee_id), true, 'row');
    $employee_type_id = $row[3];
    $row = db_read($STMT_FIND_SCHEDULE_INFO, array($employee_id, $date), true, 'row');
    $department_id = $row[3];
    $shift_id = $row[4];
    db_write($STMT_REMOVE_SCHEDULE, array($employee_id, $date));
    db_write($STMT_INCREMENT_NEED, array($department_id, $employee_type_id, $shift_id));
  } catch (Exception $e) {
    rollback();
    printf("ERROR: " . $e->getMessage() . "\n");
    return false;
  }
  commit();
  return true;
}

function requestDayOff($employee_id, $date) {
  include 'constants.php';
  try {
    begin();
    if (employee_is_schedule_on_date($employee_id, $date))
      throw new Exception('Employee is already scheduled on that day');
    db_write($STMT_REQUEST_DAY_OFF, array($employee_id, $date));
  } catch (Exception $e) {
    rollback();
    printf("ERROR: " . $e->getMessage() . "\n");
    return false;
  }
  commit();
  return true;
}

function find_suitable_employee_id($rows, $shift_id) {
  // Attempt to find one who is full time and matches shift preference
  $selected_employee_id = null;
  for ($i = 0; $i < sizeof($rows); $i++) {
    if ($rows[$i]['ftpt'] == 'FT' && $rows[$i]['preferred_shift_id'] == $shift_id) {
      $selected_employee_id = $rows[$i]['id'];
      break;
    }
  }
  if ($selected_employee_id != null)
    return $selected_employee_id;

  // If not found in previous attempt, find one who is full time
  for ($i = 0; $i < sizeof($rows); $i++) {
    if ($rows[$i]['ftpt'] == 'FT') {
      $selected_employee_id = $rows[$i]['id'];
      break;
    }
  }
  if ($selected_employee_id != null)
    return $selected_employee_id;

  // If not found in previous attempt, find one who is part time and matches shift preference
  $selected_employee_id = null;
  for ($i = 0; $i < sizeof($rows); $i++) {
    if ($rows[$i]['preferred_shift_id'] == $shift_id) {
      $selected_employee_id = $rows[$i]['id'];
      break;
    }
  }
  if ($selected_employee_id != null)
    return $selected_employee_id;

  // If not found in previous attempt, pick any one
  return $rows[0]['id'];
}

function report() {
  include 'constants.php';
  $total_schedule_cost = db_read($STMT_CALCULATE_TOTAL_COST, array(), true, 'cell');
  $average_happiness = db_read($STMT_CALCULATE_AVERAGE_HAPPINESS, array($NUM_WEEKS), true, 'cell');
  $total_shift_stats = db_read($STMT_TOTAL_SHIFT_STATS, array($NUM_WEEKS), true, 'all');
  $total_unfilled_needs = db_read($STMT_TOTAL_UNFILLED_NEEDS, array(), true, 'cell');

  $unused_shifts_full_time = $total_shift_stats[0]['total_unused_shift'];
  $unused_shifts_part_time = $total_shift_stats[1]['total_unused_shift'];
  $utilization_full_time = $total_shift_stats[0]['utitlization'];
  $utilization_part_time = $total_shift_stats[1]['utitlization'];

  printf("total_schedule_cost: $%.2f\n", $total_schedule_cost);
  printf("average_happiness: %.1f%%\n", $average_happiness * 100);
  printf("unused_shifts_full_time: %d\n", $unused_shifts_full_time);
  printf("unused_shifts_part_time: %d\n", $unused_shifts_part_time);
  printf("total_unfilled_needs: %d\n", $total_unfilled_needs);
  printf("utilization_full_time: %.1f%%\n", $utilization_full_time * 100);
  printf("utilization_part_time: %.1f%%\n", $utilization_part_time * 100);
}

function scheduleSomeone($department_id, $date, $shift_id, $employee_type_id) {
  include 'constants.php';
  try {
    begin();
    $rows = db_read($STMT_FIND_AVAILABLE_EMPLOYEE_ID, array($department_id, $employee_type_id, $date, $shift_id, $date, $shift_id, $date, $shift_id, $date, $date, $date, $date), true, 'all');
    if (sizeof($rows) == 0)
      // throw new Exception("Unable to find available employee.");
      return false;
    $employee_id = find_suitable_employee_id($rows, $shift_id);

    db_write($STMT_ADD_SCHEDULE, array($date, $employee_id, $department_id, $shift_id));
    db_write($STMT_DECREMENT_NEED, array($department_id, $employee_type_id, $shift_id));
  } catch (Exception $e) {
    rollback();
    printf("ERROR: " . $e->getMessage() . "\n");
    return false;
  }
  commit();
  return true;
}

function scheduleAll() {
  include 'constants.php';
  try {
    $rows = db_read($STMT_GET_NEEDS, array(), true, 'all');
    for ($i = 0; $i < sizeof($rows); $i++) {
      for ($j = 0; $j < $rows[$i]['employee_amount']; $j++) {
        try {
          scheduleSomeone($rows[$i]['department_id'], $rows[$i]['_date'], $rows[$i]['shift_id'], $rows[$i]['employee_type_id']);
        } catch (Exception $e) {
          printf("ERROR: " . $e->getMessage() . ". Continue to next need." . "\n");
        }
      }
    }
  } catch (Exception $e) {
    printf("ERROR: " . $e->getMessage() . "\n");
    return false;
  }
  return true;
}

$options = getopt('', array('all', 'extract', 'schedule', 'report'));
openDatabase();
if (isset($options['extract']) == TRUE) {
  initializeDatabase();
} else if (isset($options['schedule']) == TRUE) {
  scheduleAll();
} else if (isset($options['report']) == TRUE) {
  report();
} else if (isset($options['all']) == TRUE) {
  initializeDatabase();
  scheduleAll();
  report();
} else {
  print("Please find usage in README\n");
}
print("DONE.\n");
closeDatabase();
?>
