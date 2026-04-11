-- Mentor Match / IDPA SQLite (Legacy-Tabellen + schoolyears + thesis_sessions)

PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS schoolyears (
  id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
  label VARCHAR(255) NOT NULL,
  starts_on TEXT NOT NULL,
  ends_on TEXT NOT NULL,
  sections TEXT NOT NULL,
  created_at TEXT,
  updated_at TEXT
);

CREATE TABLE IF NOT EXISTS authors (
  id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
  last_name VARCHAR(50) NOT NULL,
  first_name VARCHAR(50) NOT NULL,
  class VARCHAR(16) NOT NULL,
  thesis INTEGER NOT NULL,
  email VARCHAR(50) NOT NULL,
  handy VARCHAR(20) NOT NULL,
  status INTEGER NOT NULL DEFAULT 1
);

CREATE TABLE IF NOT EXISTS supervisions (
  id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
  thesis INTEGER NOT NULL,
  teacher INTEGER NOT NULL,
  type INTEGER NOT NULL,
  datum TEXT NOT NULL,
  status INTEGER NOT NULL DEFAULT 1
);

CREATE TABLE IF NOT EXISTS teachers (
  id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
  last_name VARCHAR(50) NOT NULL,
  first_name VARCHAR(50) NOT NULL,
  token VARCHAR(7) NOT NULL,
  email VARCHAR(50) NOT NULL,
  password VARCHAR(6) NOT NULL,
  status INTEGER NOT NULL DEFAULT 1
);

CREATE TABLE IF NOT EXISTS thesis (
  id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
  title VARCHAR(100) NOT NULL,
  description TEXT NOT NULL,
  type INTEGER NOT NULL,
  password VARCHAR(10) NOT NULL,
  session INTEGER NOT NULL,
  status INTEGER NOT NULL DEFAULT 1,
  section VARCHAR(32) NOT NULL
);

CREATE TABLE IF NOT EXISTS thesis_sessions (
  id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
  schoolyear_id INTEGER REFERENCES schoolyears (id) ON DELETE RESTRICT ON UPDATE NO ACTION,
  name VARCHAR(255) NOT NULL,
  phase_1_at TEXT NOT NULL,
  phase_2_at TEXT NOT NULL,
  phase_3_at TEXT NOT NULL,
  phase_4_at TEXT NOT NULL,
  phase_5_at TEXT NOT NULL,
  closed_at TEXT,
  section_author_rules TEXT,
  compensation TEXT,
  submission_section_keys TEXT,
  created_at TEXT,
  updated_at TEXT
);
