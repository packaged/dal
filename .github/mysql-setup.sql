CREATE TABLE IF NOT EXISTS mock_ql_daos (
    id       int(11) unsigned NOT NULL AUTO_INCREMENT,
    username varchar(50) DEFAULT NULL,
    display  varchar(50) DEFAULT NULL,
    boolTest boolean,
    PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS mock_counter_daos (
    id varchar(50) NOT NULL,
    c1 int(11)        DEFAULT NULL,
    c2 int(11)        DEFAULT NULL,
    c3 decimal(10, 2) DEFAULT NULL,
    PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS mock_set_daos (
    id varchar(50) NOT NULL,
    s  SET ('one','two','three','four','five') DEFAULT NULL,
    PRIMARY KEY (`id`)
);
