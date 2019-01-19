use nzc245;
drop table if exists employee, employee_type, shift, department, department_need, day_off, schedule, department_cert cascade;

create table if not exists employee_type (
  id int auto_increment,
  name varchar(10),
  primary key (id)
);

create table if not exists shift (
  id int auto_increment,
  _from char(4),
  _length int,
  has_bonus boolean,
  primary key (id)
);

create table if not exists employee (
  id int auto_increment,
  last_name varchar(255),
  first_name varchar(255),
  hourly_wage decimal(10, 2),
  employee_type varchar(10),  -- redundant, remove later
  employee_type_id int,
  cellphone varchar(50),
  homephone varchar(50),
  ftpt char(2),
  preferred_shift varchar(20),  -- redundant, remove later
  preferred_shift_id int,
  department_cert varchar(255),  -- redundant, remove later
  primary key (id),
  foreign key (employee_type) references employee_type(id)
);

create table if not exists department (
  id int auto_increment,
  name varchar(255),
  primary key (id)
);

create table if not exists department_need (
  id int auto_increment,
  department_name varchar(255),  -- redundant, remove later
  department_id int,
  _date date,
  shift varchar(20),  -- redundant, remove later
  shift_id int,
  employee_type varchar(10),  -- redundant, remove later
  employee_type_id int,
  employee_amount int,
  primary key (id),
  foreign key (department_id) references department(id),
  foreign key (employee_type_id) references employee_type(id),
  foreign key (shift_id) references shift(id)
);

create table if not exists day_off (
  id int auto_increment,
  first_name varchar(255),  -- redundant, remove later
  last_name varchar(255),  -- redundant, remove later
  employee_id int,
  _date date,
  primary key (id),
  foreign key (employee_id) references employee(id)
);

create table if not exists schedule (
  id int auto_increment,
  _date date,
  employee_id int,
  department_name varchar(255),  -- redundant, remove later
  department_id int,
  _from char(4),  -- redundant, remove later
  _length int,  -- redundant, remove later
  shift_id int,
  primary key(id),
  foreign key (employee_id) references employee(id),
  foreign key (department_id) references department(id),
  foreign key (shift_id) references shift(id)
);

create table if not exists department_cert (
  id int auto_increment,
  employee_id int,
  department_id int,
  primary key(id),
  foreign key (employee_id) references employee(id),
  foreign key (department_id) references department(id)
);

load data local infile '/home/grads/nzc245/workdir/assignment12/employee.csv' into table employee
  FIELDS TERMINATED BY ','
  optionally enclosed by '"'
  ignore 1 lines
(@first_name, @last_name, @hourly_wage, @employee_type, @homephone, @cellphone, @ftpt, @preferred_shift, @department_cert)
set first_name = trim(@first_name),
    last_name = trim(@last_name),
    hourly_wage = convert(substring(@hourly_wage, 2, 5), decimal(10, 2)),
    employee_type = @employee_type,
    cellphone = @cellphone,
    homephone = @homephone,
    ftpt = @ftpt,
    preferred_shift = @preferred_shift,
    department_cert = trim(@department_cert)
;

load data local infile '/home/grads/nzc245/workdir/assignment11/daysoffrequests.csv' into table day_off
  FIELDS TERMINATED BY ','
  optionally enclosed by '"'
(@first_name, @last_name, @_date)
set first_name = trim(@first_name),
    last_name = trim(@last_name),
    _date = STR_TO_DATE(@_date,' %b %d %Y')
;

load data local infile '/home/grads/nzc245/workdir/assignment11/needs.csv' into table department_need
  FIELDS TERMINATED BY ','
  optionally enclosed by '"'
(@department_name, @_date, @shift, @employee_type, @employee_amount)
set department_name = @department_name,
    _date = STR_TO_DATE(@_date,' %b %d %Y'),
    shift = @shift,
    employee_type = @employee_type,
    employee_amount = @employee_amount
;


insert into employee_type (name)
select distinct employee_type
from employee
order by employee_type;

insert into shift (_from, _length, has_bonus)
select
  case
    when dn.shift like '7AM-%' then '07AM'
    when dn.shift like '3PM-%' then '03PM'
    when dn.shift like '11PM-%' then '11PM'
    when dn.shift like '7AM-%' then '07AM'
    when dn.shift like '7PM-%' then '07PM'
    else null
  end as _from,
  8 as _length,  -- since there are only length 8 shifts
  case
    when dn.shift = '11PM-7AM' then true
    else false
  end as has_bonus
from department_need dn
group by _from, _length, has_bonus
;

insert into department (name)
select distinct department_name
from department_need
order by department_name asc;


insert into department_cert (employee_id, department_id)
select e.id, d.id
from (
  select 1 n union all
  select 2 union all
  select 3 union all
  select 4 union all
  select 5 union all
  select 6 union all
  select 7 union all
  select 8
) as n
inner join employee e on char_length(e.department_cert) - char_length(replace(e.department_cert, '|', '')) >= n.n-1
inner join department d on d.name = substring_index(substring_index(e.department_cert, '|', n.n), '|', -1);

update employee e
join employee_type t on e.employee_type = t.name
set e.employee_type_id = t.id;

update employee e
join (
  select
    id,
    case  -- bad bug here
      when _from = '07AM' then '7AM-3PM'
      when _from = '03PM' then '3PM-11PM'
      when _from = '11PM' then '11PM-7AM'
      else null
    end as _shift
  from shift
) tmp on tmp._shift = e.preferred_shift
set e.preferred_shift_id = tmp.id;

alter table employee drop column employee_type;
alter table employee drop column preferred_shift;
alter table employee drop column department_cert;


update department_need dn
join department d on dn.department_name = d.name
set dn.department_id = d.id;

update department_need dn
join (
  select
    id,
    case  -- bad bug here
      when _from = '07AM' then '7AM-3PM'
      when _from = '03PM' then '3PM-11PM'
      when _from = '11PM' then '11PM-7AM'
      else null
    end as _shift
  from shift
) tmp on tmp._shift = dn.shift
set dn.shift_id = tmp.id;

update department_need dn
join employee_type t on dn.employee_type = t.name
set dn.employee_type_id = t.id;

alter table department_need drop column department_name;
alter table department_need drop column shift;
alter table department_need drop column employee_type;


update day_off do
join employee e on e.first_name = do.first_name and e.last_name = do.last_name
set do.employee_id = e.id;

alter table day_off drop column first_name;
alter table day_off drop column last_name;


update schedule sc
join department d on d.name = sc.department_name
join shift sh on sh._from = sc._from and sh._length = sc._length
set sc.department_id = d.id,
    sc.shift_id = sh.id
;
alter table schedule drop column department_name;
alter table schedule drop column _length;
alter table schedule drop column _from;


-- select * from department;
-- select * from employee limit 1;
-- select * from shift;
-- select * from employee_type;
-- select * from department_need limit 1;
-- select * from day_off limit 1;
-- select * from schedule limit 1;
-- select * from department_cert limit 1;

-- show tables;
-- describe department;
-- describe employee;
-- describe shift;
-- describe employee_type;
-- describe department_need;
-- describe day_off;
-- describe schedule;
-- describe department_cert;
