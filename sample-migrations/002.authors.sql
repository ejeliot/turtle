create table authors (
    author_id int(11) auto_increment,
    forename varchar(50),
    surname varchar(50),
    primary key (author_id)
) engine=myisam default charset=utf8;