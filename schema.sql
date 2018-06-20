create table releases
(
  id         integer not null
    primary key
  autoincrement,
  version    text    not null,
  changelog  text    not null,
  url        text    not null,
  date       integer not null,
  mc_version text    not null,
  user_id    integer not null,
  status     integer not null,
  type       text    not null
);

create table tags
(
  id    INTEGER
    primary key
  autoincrement,
  name  TEXT,
  badge TEXT
);

create table users
(
  id           integer not null
    primary key
  autoincrement,
  username     text    not null,
  password     text    not null,
  email        text    not null,
  date_created integer not null,
  role         integer not null
);

create table wiki
(
  id     integer not null
    primary key
  autoincrement,
  url    text    not null,
  name   text    not null,
  status integer not null,
  icon   text
);

create table wiki_revisions
(
  id            integer not null
    primary key
  autoincrement,
  wiki_id       integer not null,
  body          text    not null,
  user_id       integer not null,
  date          integer not null,
  reverted_by   integer not null,
  reverted_from integer not null,
  hash          text    not null
);

create table wiki_tags
(
  id         INTEGER
    primary key
  autoincrement,
  tag_id     int not null
    constraint wiki_tags_fk_tag_id
    references tags,
  wiki_id    int not null
    constraint wiki_tags_fk_wiki_id
    references wiki,
  release_id INTEGER
    constraint wiki_tags_fk_release_id
    references releases
);

