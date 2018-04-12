CREATE TABLE releases
(
  id         INTEGER NOT NULL
    PRIMARY KEY
  autoincrement,
  version    TEXT    NOT NULL,
  changelog  TEXT    NOT NULL,
  url        TEXT    NOT NULL,
  date       INTEGER NOT NULL,
  mc_version TEXT    NOT NULL,
  user_id    INTEGER NOT NULL,
  status     INTEGER NOT NULL,
  type       TEXT    NOT NULL
);

CREATE TABLE users
(
  id           INTEGER NOT NULL
    PRIMARY KEY
  autoincrement,
  username     TEXT    NOT NULL,
  password     TEXT    NOT NULL,
  email        TEXT    NOT NULL,
  date_created INTEGER NOT NULL,
  role         INTEGER NOT NULL
);

ALTER TABLE releases
  ADD CONSTRAINT releases_fk_users
FOREIGN KEY (user_id) REFERENCES users;

CREATE TABLE wiki
(
  id     INTEGER NOT NULL
    PRIMARY KEY
  autoincrement,
  url    TEXT    NOT NULL,
  name   TEXT    NOT NULL,
  status INTEGER NOT NULL,
  icon   TEXT
);

CREATE TABLE wiki_revisions
(
  id            INTEGER NOT NULL
    PRIMARY KEY
  autoincrement,
  wiki_id       INTEGER NOT NULL
  CONSTRAINT wiki_revisions_fk_wiki
  REFERENCES wiki,
  body          TEXT    NOT NULL,
  user_id       INTEGER NOT NULL
  CONSTRAINT wiki_revisions_fk_user
  REFERENCES users,
  date          INTEGER NOT NULL,
  reverted_by   INTEGER NOT NULL
  CONSTRAINT wiki_revisions_fk_user_reverted_by
  REFERENCES users,
  reverted_from INTEGER NOT NULL,
  hash          TEXT    NOT NULL
);