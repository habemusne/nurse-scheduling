<?php
$HOST = "";
$PASS = "";
$USER = "";
$DB = "";

$NUM_WEEKS = 2;

$STMT_FIND_AVAILABLE_EMPLOYEE_ID = <<<STMT
SELECT DISTINCT e.id, e.ftpt, e.preferred_shift_id, e.hourly_wage
FROM employee e
JOIN department_cert dc ON dc.employee_id = e.id
JOIN department d ON dc.department_id = d.id
LEFT JOIN (
  SELECT employee_id, COUNT(sc.id) num
  FROM employee e
  JOIN schedule sc ON e.id = sc.employee_id
  GROUP BY e.id
) used_shift on used_shift.employee_id = e.id
WHERE d.id = %s
AND e.employee_type_id = %s
AND e.id NOT IN (
  SELECT DISTINCT employee_id
  FROM day_off
  WHERE _date = "%s"
)
AND e.id NOT IN (
  SELECT DISTINCT employee_id
  FROM schedule
  WHERE (%d = 1 AND _date = "%s" AND shift_id = 2)
  OR (%d = 2 AND _date = DATE_ADD("%s", INTERVAL -1 DAY) AND shift_id = 3)
  OR (%d = 3 AND _date = "%s" AND shift_id = 1)
  OR (_date = DATE_ADD("%s", INTERVAL -1 DAY) AND WEEKDAY(_date = DATE_ADD("%s", INTERVAL -1 DAY)) = 6)
)
AND e.id NOT IN (
  SELECT id FROM (
    SELECT e.id, e.ftpt
    FROM schedule sc
    JOIN employee e ON e.id = sc.employee_id
    WHERE WEEK(sc._date, 3) = WEEK("%s", 3)
    GROUP BY e.id
    HAVING COUNT(sc.id) >= CASE WHEN e.ftpt = 'FT' THEN 5 ELSE 3 END
  ) tmp
)
ORDER BY e.ftpt asc, used_shift.num asc, e.hourly_wage asc
STMT;

$STMT_FIND_EMPLOYEE_INFO = <<<STMT
SELECT * FROM employee WHERE id = %s
STMT;

$STMT_FIND_DEPARTMENT_ID = <<<STMT
SELECT id FROM department WHERE name = "%s"
STMT;

$STMT_FIND_SHIFT_ID = <<<STMT
SELECT id FROM shift WHERE _from = "%s" AND _length = %s
STMT;

$STMT_FIND_SCHEDULE_INFO = <<<STMT
SELECT * FROM schedule WHERE employee_id = %s AND _date = "%s"
STMT;


$STMT_ADD_SCHEDULE = <<<STMT
INSERT INTO schedule (_date, employee_id, department_id, shift_id)
VALUES ("%s", %s, %s, %s)
STMT;

$STMT_REMOVE_SCHEDULE = <<<STMT
DELETE FROM schedule WHERE employee_id = %s AND _date = "%s"
STMT;

$STMT_INCREMENT_NEED = <<<STMT
UPDATE department_need
SET employee_amount = employee_amount + 1
WHERE department_id = %s AND employee_type_id = %s AND shift_id = %s
STMT;

$STMT_DECREMENT_NEED = <<<STMT
UPDATE department_need
SET employee_amount = GREATEST(employee_amount - 1, 0)
WHERE department_id = %s AND employee_type_id = %s AND shift_id = %s
STMT;

$STMT_FIND_EMPLOYEE_SCHEDULES = <<<STMT
SELECT *
FROM schedule s
WHERE s.employee_id = %s AND s._date = "%s"
STMT;

$STMT_FIND_EMPLOYEE_DAY_OFF = <<<STMT
SELECT *
FROM day_off d
WHERE d.employee_id = %s AND d._date = "%s"
STMT;

$STMT_REQUEST_DAY_OFF = <<<STMT
INSERT INTO day_off (employee_id, _date) VALUES (%s, "%s")
STMT;

$STMT_GET_NEEDS = <<<STMT
SELECT * FROM department_need;
STMT;

$STMT_CALCULATE_TOTAL_COST = <<<STMT
SELECT SUM(total_wage.num)
FROM (
  SELECT 2 * 5 * hourly_wage num
  FROM employee e
  WHERE e.ftpt = 'FT'
  UNION ALL
  SELECT COUNT(sc.id) * hourly_wage num
  FROM employee e
  JOIN schedule sc ON sc.employee_id = e.id
  WHERE ftpt = 'PT'
  GROUP BY e.id
) total_wage
STMT;

$STMT_CALCULATE_AVERAGE_HAPPINESS = <<<STMT
SELECT SUM(CASE WHEN used_shift.num IS NOT NULL THEN matched_shift.num/used_shift.num ELSE 0.5 END) / COUNT(potential_shift.employee_id) num
FROM (
  SELECT %d * CASE WHEN ftpt = 'FT' THEN 5 ELSE 3 END num, id employee_id
  FROM employee
) potential_shift
LEFT JOIN (
  SELECT COUNT(sc.id) num, sc.employee_id
  FROM schedule sc
  JOIN employee e ON e.id = sc.employee_id
  GROUP BY sc.employee_id
) used_shift
ON used_shift.employee_id = potential_shift.employee_id
LEFT JOIN (
  SELECT COUNT(sc.id) num, sc.employee_id
  FROM schedule sc
  JOIN employee e ON e.id = sc.employee_id
  WHERE e.preferred_shift_id = sc.shift_id
  GROUP BY sc.employee_id
) matched_shift
ON potential_shift.employee_id = matched_shift.employee_id
STMT;

$STMT_TOTAL_SHIFT_STATS = <<<STMT
SELECT SUM(num1 - num2) total_unused_shift, SUM(num2)/SUM(num1) utitlization
FROM (
  SELECT e.id, e.ftpt, potential_shift.num num1, used_shift.num num2
  FROM employee e
  JOIN (
    SELECT 2 * CASE WHEN ftpt = 'FT' THEN 5 ELSE 3 END num, id employee_id
    FROM employee
  ) potential_shift ON e.id = potential_shift.employee_id
  LEFT JOIN (
    SELECT COUNT(sc.id) num, sc.employee_id
    FROM schedule sc
    JOIN employee e ON e.id = sc.employee_id
    GROUP BY sc.employee_id
  ) used_shift ON e.id = used_shift.employee_id
) tmp
GROUP BY ftpt ORDER BY ftpt asc
STMT;

$STMT_TOTAL_UNFILLED_NEEDS = <<<STMT
SELECT SUM(employee_amount)
FROM department_need
WHERE employee_amount > 0
STMT;
?>
