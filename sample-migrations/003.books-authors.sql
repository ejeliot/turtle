create table books_authors (
    book_id int(11),
    author_id int(11),
    primary key (book_id, author_id)
) engine=myisam default charset=utf8;