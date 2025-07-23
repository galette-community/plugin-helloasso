--
-- Table structure for table `galette_helloasso_history`
--
DROP TABLE IF EXISTS galette_helloasso_history;
CREATE TABLE galette_helloasso_history (
  id_helloasso int(11) NOT NULL auto_increment,
  history_date datetime NOT NULL,
  checkout_id varchar(255) COLLATE utf8_unicode_ci,
  amount double NOT NULL,
  comments varchar(255)  COLLATE utf8_unicode_ci,
  request text COLLATE utf8_unicode_ci,
  state tinyint(4) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_helloasso`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

--
-- Table structure for table `galette_helloasso_preferences`
--
DROP TABLE IF EXISTS galette_helloasso_preferences;
CREATE TABLE galette_helloasso_preferences (
  id_pref int(10) unsigned NOT NULL auto_increment,
  nom_pref varchar(100) NOT NULL default '',
  val_pref varchar(200) NOT NULL default '',
  PRIMARY KEY (id_pref),
  UNIQUE KEY (nom_pref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

INSERT INTO galette_helloasso_preferences (nom_pref, val_pref) VALUES ('helloasso_test_mode', '');
INSERT INTO galette_helloasso_preferences (nom_pref, val_pref) VALUES ('helloasso_organization_slug', '');
INSERT INTO galette_helloasso_preferences (nom_pref, val_pref) VALUES ('helloasso_client_id', '');
INSERT INTO galette_helloasso_preferences (nom_pref, val_pref) VALUES ('helloasso_client_secret', '');
INSERT INTO galette_helloasso_preferences (nom_pref, val_pref) VALUES ('helloasso_inactives', '4,6,7');

--
-- Table structure for table `galette_helloasso_tokens`
--
DROP TABLE IF EXISTS galette_helloasso_tokens;
CREATE TABLE galette_helloasso_tokens (
  id int(10) unsigned NOT NULL auto_increment,
  type varchar(100) NOT NULL default '',
  value text NOT NULL,
  expiry datetime NULL,
  PRIMARY KEY (id),
  UNIQUE KEY(type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

INSERT INTO galette_helloasso_tokens (type, value, expiry) VALUES ('access_token', '', NULL);
INSERT INTO galette_helloasso_tokens (type, value, expiry) VALUES ('refresh_token', '', NULL);
