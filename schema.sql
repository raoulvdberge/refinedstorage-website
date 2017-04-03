-- Adminer 4.2.5 SQLite 3 dump

DROP TABLE IF EXISTS "releases";
CREATE TABLE "releases" (
  "id" integer NOT NULL PRIMARY KEY AUTOINCREMENT,
  "version" text NOT NULL,
  "changelog" text NOT NULL,
  "url" text NOT NULL,
  "date" integer NOT NULL,
  "mc_version" text NOT NULL,
  "user_id" integer NOT NULL,
  "status" integer NOT NULL,
  "type" text NOT NULL
);


DROP TABLE IF EXISTS "users";
CREATE TABLE "users" (
  "id" integer NOT NULL PRIMARY KEY AUTOINCREMENT,
  "username" text NOT NULL,
  "password" text NOT NULL,
  "email" text NOT NULL,
  "date_created" integer NOT NULL,
  "role" integer NOT NULL
);


DROP TABLE IF EXISTS "wiki";
CREATE TABLE "wiki" (
  "id" integer NOT NULL PRIMARY KEY AUTOINCREMENT,
  "url" text NOT NULL,
  "name" text NOT NULL,
  "status" integer NOT NULL
, "icon" text NULL);


DROP TABLE IF EXISTS "wiki_revisions";
CREATE TABLE "wiki_revisions" (
  "id" integer NOT NULL PRIMARY KEY AUTOINCREMENT,
  "wiki_id" integer NOT NULL,
  "body" text NOT NULL,
  "user_id" integer NOT NULL,
  "date" integer NOT NULL,
  "reverted_by" integer NOT NULL,
  "reverted_from" integer NOT NULL,
  "hash" text NOT NULL
);


-- 
