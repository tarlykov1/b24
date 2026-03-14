CREATE TABLE b_tasks (
  ID INTEGER PRIMARY KEY,
  CREATED_DATE TEXT,
  CHANGED_DATE TEXT,
  ZOMBIE TEXT
);
CREATE TABLE b_crm_deal (
  ID INTEGER PRIMARY KEY,
  DATE_CREATE TEXT,
  DATE_MODIFY TEXT
);
CREATE TABLE b_user (
  ID INTEGER PRIMARY KEY,
  DATE_REGISTER TEXT,
  TIMESTAMP_X TEXT
);
CREATE TABLE b_disk_object (
  ID INTEGER PRIMARY KEY,
  CREATE_TIME TEXT,
  UPDATE_TIME TEXT,
  DELETED_TYPE TEXT
);
CREATE TABLE b_file (
  ID INTEGER PRIMARY KEY,
  TIMESTAMP_X TEXT
);
CREATE TABLE b_sonet_group (
  ID INTEGER PRIMARY KEY,
  DATE_CREATE TEXT,
  DATE_MODIFY TEXT
);

INSERT INTO b_tasks(ID, CREATED_DATE, CHANGED_DATE, ZOMBIE) VALUES
(1, datetime('now','-10 days'), datetime('now','-2 hours'), NULL),
(2, datetime('now','-1 days'), datetime('now','-1 hours'), NULL),
(3, datetime('now','-20 days'), datetime('now','-15 days'), 'Y');

INSERT INTO b_crm_deal(ID, DATE_CREATE, DATE_MODIFY) VALUES
(1, datetime('now','-3 days'), datetime('now','-2 days')),
(2, datetime('now','-12 hours'), datetime('now','-4 hours'));

INSERT INTO b_user(ID, DATE_REGISTER, TIMESTAMP_X) VALUES
(1, datetime('now','-100 days'), datetime('now','-3 days')),
(2, datetime('now','-20 days'), datetime('now','-1 days'));

INSERT INTO b_disk_object(ID, CREATE_TIME, UPDATE_TIME, DELETED_TYPE) VALUES
(1, datetime('now','-2 days'), datetime('now','-1 days'), NULL),
(2, datetime('now','-50 days'), datetime('now','-45 days'), 'deleted');

INSERT INTO b_file(ID, TIMESTAMP_X) VALUES
(1, datetime('now','-4 hours')),
(2, datetime('now','-15 days'));

INSERT INTO b_sonet_group(ID, DATE_CREATE, DATE_MODIFY) VALUES
(1, datetime('now','-300 days'), datetime('now','-1 days'));
