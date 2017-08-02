<?php
		$schema = <<<SQL
		
		CREATE TABLE $this->language_table (
		  language_id tinyint(3) NOT NULL auto_increment,
		  language varchar(255) NOT NULL default '',
			code varchar(4) NOT NULL default '',
			locale varchar(7) NOT NULL default '',
			rtl tinyint(1) NOT NULL default 0,
			charset varchar(16) NOT NULL default '',
			UNIQUE (code),
		  PRIMARY KEY  (language_id)
		) TYPE=MyISAM;

		CREATE TABLE $this->post2lang_table (
		  post_id bigint(20) NOT NULL default '0',
			language_id tinyint(3) NOT NULL default '0',
			translation_group mediumint(6) NOT NULL default '0',
			summary_group mediumint(6) NOT NULL default '0',
		  PRIMARY KEY  (post_id),
		  KEY language_idx (language_id)
		) TYPE=MyISAM;

		CREATE TABLE $this->summary_table (
		  summary_id mediumint(6) NOT NULL auto_increment,
		  summary_group mediumint(6) NOT NULL default '0',
			language_id tinyint(3) NOT NULL default '0',
		  summary longtext NOT NULL,
		  PRIMARY KEY  (summary_id)
		) TYPE=MyISAM;

		CREATE TABLE $this->synblock_table (
			block_name varchar(55) NOT NULL default '',
			language_id tinyint(3) NOT NULL default '0',
			text longtext NOT NULL,
		  PRIMARY KEY  (block_name,language_id)
		) TYPE=MyISAM;

		CREATE TABLE $this->term2syn_table (
			term_id bigint(20) NOT NULL default '0',
			language_id tinyint(3) NOT NULL default '0',
			synonym varchar(55) NOT NULL default '',
			sanitised varchar(200) NOT NULL default '',
			description longtext NOT NULL default '',
		  PRIMARY KEY  (term_id,language_id)
		) TYPE=MyISAM;

SQL;
?>
