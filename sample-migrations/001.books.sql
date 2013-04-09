create table books (
    book_id int(11) auto_increment,
    title varchar(250),
    description text,
    price decimal(3, 2),
    primary key (book_id)
) engine=myisam default charset=utf8;