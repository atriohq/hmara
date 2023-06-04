<?php

// Title of the form.
$form['title'] = 'External Domain';
$form['description'] = '';
$form['name'] = 'domain_verification';
$form['action'] = 'domain_verification_edit.php';
$form['db_table'] = 'domain_verification';
$form['db_table_idx'] = 'domain_id';
$form['db_history'] = 'no';
$form['tab_default'] = 'domain_verification';
$form['list_default'] = 'domain_verification_info.php';
$form['auth'] = 'yes';

//* Authentication presets. The defaults below does not need to be changed in most cases.
$form["auth_preset"]["userid"] = 0; // 0 = id of the user, > 0 id must match with id of current user
$form["auth_preset"]["groupid"] = 0; // 0 = default groupid of the user, > 0 id must match with groupid of current user
$form["auth_preset"]["perm_user"] = 'riud'; //r = read, i = insert, u = update, d = delete
$form["auth_preset"]["perm_group"] = 'riud'; //r = read, i = insert, u = update, d = delete
$form["auth_preset"]["perm_other"] = ''; //r = read, i = insert, u = update, d = delete



$form['tabs']['domain_verification'] = array(
	'title' => 'External Domain', // Title of the Tab
	'width' => 100, // Tab width
	'template' => 'templates/domain_verification_edit.htm', // Template file name
	'fields' => array(
		'domain' => array(
			'datatype' => 'VARCHAR',
			'formtype' => 'TEXT',
			'filters' => array(0 => array('event' => 'SAVE',
					'type' => 'IDNTOASCII'),
				1 => array('event' => 'SHOW',
					'type' => 'IDNTOUTF8'),
				2 => array('event' => 'SAVE',
					'type' => 'TOLOWER')
			),
			'validators' => array(0 => array('type' => 'NOTEMPTY',
					'errmsg' => 'domain_error_empty'),
				1 => array('type' => 'UNIQUE',
					'errmsg' => 'domain_error_unique'),
				2 => array('type' => 'ISDOMAIN',
					'errmsg' => 'domain_error_regex'),
			),
			'default' => '',
			'value' => '',
			'width' => '30',
			'maxlength' => '255',
			'searchable' => 1
		),
		'dns_auth_record' => array(
			'datatype' => 'VARCHAR',
			'formtype' => 'TEXT',
			'default' => '',
			'value' => '',
			'width' => '50',
			'maxlength' => '255'
		),
		'record_created' => array(
			'datatype' => 'TIMESTAMP',
			'formtype' => 'TEXT',
			'default' => '',
			'value' => '',
			'width' => '50',
			'maxlength' => '255'
		),
	)
);
?>