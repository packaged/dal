CREATE KEYSPACE IF NOT EXISTS packaged_dal WITH REPLICATION = {'class': 'SimpleStrategy', 'replication_factor': '1'};
CREATE KEYSPACE IF NOT EXISTS packaged_dal_switch WITH REPLICATION = {'class': 'SimpleStrategy', 'replication_factor': '1'};

DROP TABLE IF EXISTS packaged_dal.mock_ql_daos;
CREATE TABLE packaged_dal.mock_ql_daos (
    id            varchar,
    id2           int,
    username      varchar,
    display       varchar,
    intVal        int,
    bigintVal     bigint,
    doubleVal     double,
    floatVal      float,
    decimalVal    decimal,
    newDecimalVal decimal,
    timestampVal  timestamp,
    boolVal       boolean,
    PRIMARY KEY ( (id),id2 )
);

DROP TABLE IF EXISTS packaged_dal.mock_counter_daos;
CREATE TABLE packaged_dal.mock_counter_daos (
    id varchar PRIMARY KEY,
    c1 counter,
    c2 counter,
);

DROP TABLE IF EXISTS packaged_dal.mock_set_daos;
CREATE TABLE packaged_dal.mock_set_daos (
    id varchar PRIMARY KEY,
    s  set<varchar>,
);
