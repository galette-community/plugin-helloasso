--
-- Table structure for table galette_helloasso_history
--
DROP SEQUENCE IF EXISTS galette_helloasso_history_id_seq;
CREATE SEQUENCE galette_helloasso_history_id_seq
    START 1
    INCREMENT 1
    MAXVALUE 2147483647
    MINVALUE 1
    CACHE 1;

DROP TABLE IF EXISTS galette_helloasso_history;
CREATE TABLE galette_helloasso_history (
  id_helloasso integer DEFAULT nextval('galette_helloasso_history_id_seq'::text) NOT NULL,
  history_date date NOT NULL,
  checkout_id character varying(255),
  amount real NOT NULL,
  comments character varying(255),
  request text,
  state smallint DEFAULT 0 NOT NULL,
  PRIMARY KEY (id_helloasso)
);

--
-- Table structure for table `galette_helloasso_preferences`
--
DROP SEQUENCE IF EXISTS galette_helloasso_preferences_id_seq;
CREATE SEQUENCE galette_helloasso_preferences_id_seq
    START 1
    INCREMENT 1
    MAXVALUE 2147483647
    MINVALUE 1
    CACHE 1;

DROP TABLE IF EXISTS galette_helloasso_preferences;
CREATE TABLE galette_helloasso_preferences (
  id_pref integer DEFAULT nextval('galette_helloasso_preferences_id_seq'::text) NOT NULL,
  nom_pref character varying(100) NOT NULL default '',
  val_pref character varying(200) NOT NULL default '',
  PRIMARY KEY  (id_pref)
);

CREATE UNIQUE INDEX galette_helloasso_preferences_unique_idx ON galette_helloasso_preferences (nom_pref);

INSERT INTO galette_helloasso_preferences (nom_pref, val_pref) VALUES ('helloasso_test_mode', '');
INSERT INTO galette_helloasso_preferences (nom_pref, val_pref) VALUES ('helloasso_organization_slug', '');
INSERT INTO galette_helloasso_preferences (nom_pref, val_pref) VALUES ('helloasso_client_id', '');
INSERT INTO galette_helloasso_preferences (nom_pref, val_pref) VALUES ('helloasso_client_secret', '');
INSERT INTO galette_helloasso_preferences (nom_pref, val_pref) VALUES ('helloasso_inactives', '4,6,7');

--
-- Table structure for table `galette_helloasso_tokens`
--
DROP SEQUENCE IF EXISTS galette_helloasso_tokens_id_seq;
CREATE SEQUENCE galette_helloasso_tokens_id_seq
    START 1
    INCREMENT 1
    MAXVALUE 2147483647
    MINVALUE 1
    CACHE 1;

DROP TABLE IF EXISTS galette_helloasso_tokens;
CREATE TABLE galette_helloasso_tokens (
  id integer DEFAULT nextval('galette_helloasso_tokens_id_seq'::text) NOT NULL,
  type character varying(100) NOT NULL default '',
  value text NOT NULL default '',
  expiry timestamp NULL,
  PRIMARY KEY (id)
);

CREATE UNIQUE INDEX galette_helloasso_tokens_unique_idx ON galette_helloasso_tokens (type);

INSERT INTO galette_helloasso_tokens (type, value, expiry) VALUES ('access_token', '', NULL);
INSERT INTO galette_helloasso_tokens (type, value, expiry) VALUES ('refresh_token', '', NULL);
