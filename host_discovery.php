<?php
/*
** Netafier
** Copyright (C) 2001-2020 Neafier .JSC
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


require_once dirname(__FILE__).'/include/config.inc.php';
require_once dirname(__FILE__).'/include/hosts.inc.php';
require_once dirname(__FILE__).'/include/items.inc.php';
require_once dirname(__FILE__).'/include/forms.inc.php';

$page['title'] = _('Configuration of discovery rules');
$page['file'] = 'host_discovery.php';
$page['scripts'] = ['class.cviewswitcher.js', 'multilineinput.js', 'multiselect.js', 'items.js', 'textareaflexible.js'];

require_once dirname(__FILE__).'/include/page_header.php';

$paramsFieldName = getParamFieldNameByType(getRequest('type', 0));

// supported eval types
$evalTypes = [
	CONDITION_EVAL_TYPE_AND_OR,
	CONDITION_EVAL_TYPE_AND,
	CONDITION_EVAL_TYPE_OR,
	CONDITION_EVAL_TYPE_EXPRESSION
];

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = [
	'hostid' =>					[T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		'isset({form}) && !isset({itemid})'],
	'itemid' =>					[T_ZBX_INT, O_NO,	P_SYS,	DB_ID,		'(isset({form}) && ({form} == "update"))'],
	'interfaceid' =>			[T_ZBX_INT, O_OPT, P_SYS,	DB_ID, null, _('Interface')],
	'name' =>					[T_ZBX_STR, O_OPT, null,	NOT_EMPTY, 'isset({add}) || isset({update})', _('Name')],
	'description' =>			[T_ZBX_STR, O_OPT, null,	null,		'isset({add}) || isset({update})'],
	'key' =>					[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,	'isset({add}) || isset({update})', _('Key')],
	'master_itemid' =>			[T_ZBX_STR, O_OPT, null,	null,
									'(isset({add}) || isset({update})) && isset({type})'.
										' && {type} == '.ITEM_TYPE_DEPENDENT,
									_('Master item')
								],
	'delay' =>					[T_ZBX_TU, O_OPT, P_ALLOW_USER_MACRO, null,
									'(isset({add}) || isset({update})) && isset({type})'.
										' && {type} != '.ITEM_TYPE_TRAPPER.' && {type} != '.ITEM_TYPE_SNMPTRAP.
										' && {type} != '.ITEM_TYPE_DEPENDENT,
									_('Update interval')
								],
	'delay_flex' =>				[T_ZBX_STR, O_OPT, null,	null,			null],
	'status' =>					[T_ZBX_INT, O_OPT, null,	IN(ITEM_STATUS_ACTIVE), null],
	'type' =>					[T_ZBX_INT, O_OPT, null,
									IN([-1, ITEM_TYPE_NETAFIER, ITEM_TYPE_TRAPPER, ITEM_TYPE_SIMPLE, ITEM_TYPE_INTERNAL,
										ITEM_TYPE_NETAFIER_ACTIVE, ITEM_TYPE_EXTERNAL, ITEM_TYPE_DB_MONITOR,
										ITEM_TYPE_IPMI, ITEM_TYPE_SSH, ITEM_TYPE_TELNET, ITEM_TYPE_JMX,
										ITEM_TYPE_DEPENDENT, ITEM_TYPE_HTTPAGENT, ITEM_TYPE_SNMP
									]),
									'isset({add}) || isset({update})'
								],
	'authtype' =>				[T_ZBX_INT, O_OPT, null,	IN(ITEM_AUTHTYPE_PASSWORD.','.ITEM_AUTHTYPE_PUBLICKEY),
		'(isset({add}) || isset({update})) && isset({type}) && {type} == '.ITEM_TYPE_SSH],
	'username' =>				[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,
									'(isset({add}) || isset({update})) && isset({type})'.
										' && '.IN(ITEM_TYPE_SSH.','.ITEM_TYPE_TELNET, 'type'),
									_('User name')
								],
	'password' =>				[T_ZBX_STR, O_OPT, null,	null,
									'(isset({add}) || isset({update})) && isset({type})'.
										' && '.IN(ITEM_TYPE_SSH.','.ITEM_TYPE_TELNET, 'type')
								],
	'publickey' =>				[T_ZBX_STR, O_OPT, null,	null,
									'(isset({add}) || isset({update})) && isset({type}) && {type} == '.ITEM_TYPE_SSH.
										' && {authtype} == '.ITEM_AUTHTYPE_PUBLICKEY
								],
	'privatekey' =>				[T_ZBX_STR, O_OPT, null,	null,
									'(isset({add}) || isset({update})) && isset({type}) && {type} == '.ITEM_TYPE_SSH.
										' && {authtype} == '.ITEM_AUTHTYPE_PUBLICKEY
								],
	$paramsFieldName =>			[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,	'(isset({add}) || isset({update}))'.
									' && isset({type}) && '.IN(ITEM_TYPE_SSH.','.ITEM_TYPE_DB_MONITOR.','.
										ITEM_TYPE_TELNET.','.ITEM_TYPE_CALCULATED, 'type'
									),
									getParamFieldLabelByType(getRequest('type', 0))
								],
	'snmp_oid' =>				[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,
									'(isset({add}) || isset({update})) && isset({type})'.
										' && {type} == '.ITEM_TYPE_SNMP,
									_('SNMP OID')
								],
	'ipmi_sensor' =>			[T_ZBX_STR, O_OPT, P_NO_TRIM, null,
									'(isset({add}) || isset({update})) && isset({type}) && {type} == '.ITEM_TYPE_IPMI,
									_('IPMI sensor')
								],
	'trapper_hosts' =>			[T_ZBX_STR, O_OPT, null,	null,
									'(isset({add}) || isset({update})) && isset({type}) && {type} == 2'
								],
	'lifetime' =>				[T_ZBX_STR, O_OPT, null,	null,		'isset({add}) || isset({update})'],
	'evaltype' =>				[T_ZBX_INT, O_OPT, null, 	IN($evalTypes), 'isset({add}) || isset({update})'],
	'formula' =>				[T_ZBX_STR, O_OPT, null,	null,		'isset({add}) || isset({update})'],
	'conditions' =>				[T_ZBX_STR, O_OPT, P_SYS,	null,		null],
	'lld_macro_paths' =>		[T_ZBX_STR, O_OPT, P_SYS,	null,		null],
	'jmx_endpoint' =>			[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,
		'(isset({add}) || isset({update})) && isset({type}) && {type} == '.ITEM_TYPE_JMX
	],
	'timeout' =>				[T_ZBX_STR, O_OPT, null,	null,		null],
	'url' =>					[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,
									'(isset({add}) || isset({update})) && isset({type})'.
										' && {type} == '.ITEM_TYPE_HTTPAGENT,
									_('URL')
								],
	'query_fields' =>			[T_ZBX_STR, O_OPT, null,	null,		null],
	'posts' =>					[T_ZBX_STR, O_OPT, null,	null,		null],
	'status_codes' =>			[T_ZBX_STR, O_OPT, null,	null,		null],
	'follow_redirects' =>		[T_ZBX_INT, O_OPT, null,
									IN([HTTPTEST_STEP_FOLLOW_REDIRECTS_OFF, HTTPTEST_STEP_FOLLOW_REDIRECTS_ON]),
									null
								],
	'post_type' =>				[T_ZBX_INT, O_OPT, null,
									IN([ZBX_POSTTYPE_RAW, ZBX_POSTTYPE_JSON, ZBX_POSTTYPE_XML]),
									null
								],
	'http_proxy' =>				[T_ZBX_STR, O_OPT, null,	null,		null],
	'headers' => 				[T_ZBX_STR, O_OPT, null,	null,		null],
	'retrieve_mode' =>			[T_ZBX_INT, O_OPT, null,
									IN([HTTPTEST_STEP_RETRIEVE_MODE_CONTENT, HTTPTEST_STEP_RETRIEVE_MODE_HEADERS,
										HTTPTEST_STEP_RETRIEVE_MODE_BOTH
									]),
									null
								],
	'request_method' =>			[T_ZBX_INT, O_OPT, null,
									IN([HTTPCHECK_REQUEST_GET, HTTPCHECK_REQUEST_POST, HTTPCHECK_REQUEST_PUT,
										HTTPCHECK_REQUEST_HEAD
									]),
									null
								],
	'allow_traps' =>			[T_ZBX_INT, O_OPT, null,	IN([HTTPCHECK_ALLOW_TRAPS_OFF, HTTPCHECK_ALLOW_TRAPS_ON]),
									null
								],
	'ssl_cert_file' =>			[T_ZBX_STR, O_OPT, null,	null,		null],
	'ssl_key_file' =>			[T_ZBX_STR, O_OPT, null,	null,		null],
	'ssl_key_password' =>		[T_ZBX_STR, O_OPT, null,	null,		null],
	'verify_peer' =>			[T_ZBX_INT, O_OPT, null,
									IN([HTTPTEST_VERIFY_PEER_OFF, HTTPTEST_VERIFY_PEER_ON]),
									null
								],
	'verify_host' =>			[T_ZBX_INT, O_OPT, null,
									IN([HTTPTEST_VERIFY_HOST_OFF, HTTPTEST_VERIFY_HOST_ON]),
									null
								],
	'http_authtype' =>			[T_ZBX_INT, O_OPT, null,
									IN([HTTPTEST_AUTH_NONE, HTTPTEST_AUTH_BASIC, HTTPTEST_AUTH_NTLM,
										HTTPTEST_AUTH_KERBEROS
									]),
									null
								],
	'http_username' =>			[T_ZBX_STR, O_OPT, null,	null,
									'(isset({add}) || isset({update})) && isset({http_authtype})'.
										' && ({http_authtype} == '.HTTPTEST_AUTH_BASIC.
											' || {http_authtype} == '.HTTPTEST_AUTH_NTLM.
											' || {http_authtype} == '.HTTPTEST_AUTH_KERBEROS.')',
									_('Username')
								],
	'http_password' =>			[T_ZBX_STR, O_OPT, null,	null,
									'(isset({add}) || isset({update})) && isset({http_authtype})'.
										' && ({http_authtype} == '.HTTPTEST_AUTH_BASIC.
											' || {http_authtype} == '.HTTPTEST_AUTH_NTLM.
											' || {http_authtype} == '.HTTPTEST_AUTH_KERBEROS.')',
									_('Password')
								],
	'preprocessing' =>			[T_ZBX_STR, O_OPT, P_NO_TRIM,	null,	null],
	'overrides' =>				[T_ZBX_STR, O_OPT, P_NO_TRIM,	null,	null],
	// actions
	'action' =>					[T_ZBX_STR, O_OPT, P_SYS|P_ACT,
									IN('"discoveryrule.massdelete","discoveryrule.massdisable",'.
										'"discoveryrule.massenable","discoveryrule.masscheck_now"'
									),
									null
								],
	'g_hostdruleid' =>			[T_ZBX_INT, O_OPT, null,	DB_ID,		null],
	'add' =>					[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'update' =>					[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'clone' =>					[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'delete' =>					[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'cancel' =>					[T_ZBX_STR, O_OPT, P_SYS,	null,		null],
	'check_now' =>				[T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null],
	'form' =>					[T_ZBX_STR, O_OPT, P_SYS,	null,		null],
	'form_refresh' =>			[T_ZBX_INT, O_OPT, null,	null,		null],
	// filter
	'filter_set' =>				[T_ZBX_STR, O_OPT, null,	null,		null],
	'filter_rst' =>				[T_ZBX_STR, O_OPT, null,	null,		null],
	'filter_groupids' =>		[T_ZBX_INT, O_OPT, null,	DB_ID,		null],
	'filter_hostids' =>			[T_ZBX_INT, O_OPT, null,	DB_ID,		null],
	'filter_name' =>			[T_ZBX_STR, O_OPT, null,	null,		null],
	'filter_key' =>				[T_ZBX_STR, O_OPT, null,	null,		null],
	'filter_type' =>			[T_ZBX_INT, O_OPT, null,
									IN([-1, ITEM_TYPE_NETAFIER, ITEM_TYPE_TRAPPER, ITEM_TYPE_SIMPLE, ITEM_TYPE_INTERNAL,
										ITEM_TYPE_NETAFIER_ACTIVE, ITEM_TYPE_EXTERNAL, ITEM_TYPE_DB_MONITOR,
										ITEM_TYPE_IPMI, ITEM_TYPE_SSH, ITEM_TYPE_TELNET, ITEM_TYPE_JMX,
										ITEM_TYPE_DEPENDENT, ITEM_TYPE_HTTPAGENT, ITEM_TYPE_SNMP
									]),
									null
								],
	'filter_delay' =>			[T_ZBX_STR, O_OPT, P_UNSET_EMPTY, null, null, _('Update interval')],
	'filter_lifetime' =>		[T_ZBX_STR, O_OPT, null,	null,		null],
	'filter_snmp_oid' =>		[T_ZBX_STR, O_OPT, null,	null,		null],
	'filter_state' =>			[T_ZBX_INT, O_OPT, null,	IN([-1, ITEM_STATE_NORMAL, ITEM_STATE_NOTSUPPORTED]),
									null
								],
	'filter_status' =>			[T_ZBX_INT, O_OPT, null,	IN([-1, ITEM_STATUS_ACTIVE, ITEM_STATUS_DISABLED]),
									null
								],
	// sort and sortorder
	'sort' =>					[T_ZBX_STR, O_OPT, P_SYS, IN('"delay","key_","name","status","type"'),	null],
	'sortorder' =>				[T_ZBX_STR, O_OPT, P_SYS, IN('"'.ZBX_SORT_DOWN.'","'.ZBX_SORT_UP.'"'),	null]
];
check_fields($fields);

$_REQUEST['params'] = getRequest($paramsFieldName, '');
unset($_REQUEST[$paramsFieldName]);
$item = [];

/*
 * Permissions
 */
$hostid = getRequest('hostid', 0);

if (getRequest('itemid', false)) {
	$item = API::DiscoveryRule()->get([
		'itemids' => getRequest('itemid'),
		'output' => API_OUTPUT_EXTEND,
		'selectHosts' => ['hostid', 'name', 'status', 'flags'],
		'selectFilter' => ['formula', 'evaltype', 'conditions'],
		'selectLLDMacroPaths' => ['lld_macro', 'path'],
		'selectPreprocessing' => ['type', 'params', 'error_handler', 'error_handler_params'],
		'selectOverrides' => ['name', 'step', 'stop', 'filter', 'operations'],
		'editable' => true
	]);
	$item = reset($item);
	if (!$item) {
		access_deny();
	}
	$_REQUEST['hostid'] = $item['hostid'];
	$host = reset($item['hosts']);
}
elseif ($hostid) {
	$hosts = API::Host()->get([
		'output' => ['hostid', 'name', 'status'],
		'hostids' => $hostid,
		'templated_hosts' => true,
		'editable' => true
	]);
	$host = reset($hosts);
	if (!$host) {
		access_deny();
	}
}

/**
 * Filter.
 */
$sort_field = getRequest('sort', CProfile::get('web.'.$page['file'].'.sort', 'name'));
$sort_order = getRequest('sortorder', CProfile::get('web.'.$page['file'].'.sortorder', ZBX_SORT_UP));

CProfile::update('web.'.$page['file'].'.sort', $sort_field, PROFILE_TYPE_STR);
CProfile::update('web.'.$page['file'].'.sortorder', $sort_order, PROFILE_TYPE_STR);

if (hasRequest('filter_set')) {
	CProfile::updateArray('web.host_discovery.filter.groupids', getRequest('filter_groupids', []), PROFILE_TYPE_ID);
	CProfile::updateArray('web.host_discovery.filter.hostids', getRequest('filter_hostids', []), PROFILE_TYPE_ID);
	CProfile::update('web.host_discovery.filter.name', getRequest('filter_name', ''), PROFILE_TYPE_STR);
	CProfile::update('web.host_discovery.filter.key', getRequest('filter_key', ''), PROFILE_TYPE_STR);
	CProfile::update('web.host_discovery.filter.type', getRequest('filter_type', -1), PROFILE_TYPE_INT);
	CProfile::update('web.host_discovery.filter.delay', getRequest('filter_delay', ''), PROFILE_TYPE_STR);
	CProfile::update('web.host_discovery.filter.lifetime', getRequest('filter_lifetime', ''), PROFILE_TYPE_STR);
	CProfile::update('web.host_discovery.filter.snmp_oid', getRequest('filter_snmp_oid', ''), PROFILE_TYPE_STR);
	CProfile::update('web.host_discovery.filter.state', getRequest('filter_state', -1), PROFILE_TYPE_INT);
	CProfile::update('web.host_discovery.filter.status', getRequest('filter_status', -1), PROFILE_TYPE_INT);
}
elseif (hasRequest('filter_rst')) {
	CProfile::deleteIdx('web.host_discovery.filter.groupids');

	if (count(CProfile::getArray('web.host_discovery.filter.hostids', [])) != 1) {
		CProfile::deleteIdx('web.host_discovery.filter.hostids');
	}

	CProfile::delete('web.host_discovery.filter.name');
	CProfile::delete('web.host_discovery.filter.key');
	CProfile::delete('web.host_discovery.filter.type');
	CProfile::delete('web.host_discovery.filter.delay');
	CProfile::delete('web.host_discovery.filter.lifetime');
	CProfile::delete('web.host_discovery.filter.snmp_oid');
	CProfile::delete('web.host_discovery.filter.state');
	CProfile::delete('web.host_discovery.filter.status');
}

$filter = [
	'groups' => CProfile::getArray('web.host_discovery.filter.groupids', []),
	'hosts' => CProfile::getArray('web.host_discovery.filter.hostids', []),
	'name' => CProfile::get('web.host_discovery.filter.name', ''),
	'key' => CProfile::get('web.host_discovery.filter.key', ''),
	'type' => CProfile::get('web.host_discovery.filter.type', -1),
	'delay' => CProfile::get('web.host_discovery.filter.delay', ''),
	'lifetime' => CProfile::get('web.host_discovery.filter.lifetime', ''),
	'snmp_oid' => CProfile::get('web.host_discovery.filter.snmp_oid', ''),
	'state' => CProfile::get('web.host_discovery.filter.state', -1),
	'status' => CProfile::get('web.host_discovery.filter.status', -1)
];

$filter_groupids = [];
$filter_hostids = [];

// Get host groups.
if ($filter['groups']) {
	$filter['groups'] = CArrayHelper::renameObjectsKeys(API::HostGroup()->get([
		'output' => ['groupid', 'name'],
		'groupids' => $filter['groups'],
		'editable' => true,
		'preservekeys' => true
	]), ['groupid' => 'id']);

	$filter_groupids = getSubGroups(array_keys($filter['groups']));
}

// Get hosts.
if ($filter['hosts']) {
	$filter['hosts'] = CArrayHelper::renameObjectsKeys(API::Host()->get([
		'output' => ['hostid', 'name'],
		'hostids' => $filter['hosts'],
		'templated_hosts' => true,
		'editable' => true,
		'preservekeys' => true
	]), ['hostid' => 'id']);

	$filter_hostids = array_keys($filter['hosts']);

	sort($filter_hostids);
}

$checkbox_hash = crc32(implode('', $filter_hostids));

// Convert CR+LF to LF in preprocessing script.
if (hasRequest('preprocessing')) {
	foreach ($_REQUEST['preprocessing'] as &$step) {
		if ($step['type'] == ZBX_PREPROC_SCRIPT) {
			$step['params'][0] = CRLFtoLF($step['params'][0]);
		}
	}
	unset($step);
}

/*
 * Actions
 */
if (hasRequest('delete') && hasRequest('itemid')) {
	$result = API::DiscoveryRule()->delete([getRequest('itemid')]);

	if ($result) {
		uncheckTableRows($checkbox_hash);
	}
	show_messages($result, _('Discovery rule deleted'), _('Cannot delete discovery rule'));

	unset($_REQUEST['itemid'], $_REQUEST['form']);
}
elseif (hasRequest('check_now') && hasRequest('itemid')) {
	$result = (bool) API::Task()->create([
		'type' => ZBX_TM_TASK_CHECK_NOW,
		'itemids' => getRequest('itemid')
	]);

	show_messages($result, _('Request sent successfully'), _('Cannot send request'));
}
elseif (hasRequest('add') || hasRequest('update')) {
	$result = true;

	$delay = getRequest('delay', DB::getDefault('items', 'delay'));
	$type = getRequest('type', ITEM_TYPE_NETAFIER);

	/*
	 * "delay_flex" is a temporary field that collects flexible and scheduling intervals separated by a semicolon.
	 * In the end, custom intervals together with "delay" are stored in the "delay" variable.
	 */
	if ($type != ITEM_TYPE_TRAPPER && $type != ITEM_TYPE_SNMPTRAP && hasRequest('delay_flex')) {
		$intervals = [];
		$simple_interval_parser = new CSimpleIntervalParser(['usermacros' => true]);
		$time_period_parser = new CTimePeriodParser(['usermacros' => true]);
		$scheduling_interval_parser = new CSchedulingIntervalParser(['usermacros' => true]);

		foreach (getRequest('delay_flex') as $interval) {
			if ($interval['type'] == ITEM_DELAY_FLEXIBLE) {
				if ($interval['delay'] === '' && $interval['period'] === '') {
					continue;
				}

				if ($simple_interval_parser->parse($interval['delay']) != CParser::PARSE_SUCCESS) {
					$result = false;
					info(_s('Invalid interval "%1$s".', $interval['delay']));
					break;
				}

				if ($time_period_parser->parse($interval['period']) != CParser::PARSE_SUCCESS) {
					$result = false;
					info(_s('Invalid interval "%1$s".', $interval['period']));
					break;
				}

				$intervals[] = $interval['delay'].'/'.$interval['period'];
			}
			else {
				if ($interval['schedule'] === '') {
					continue;
				}

				if ($scheduling_interval_parser->parse($interval['schedule']) != CParser::PARSE_SUCCESS) {
					$result = false;
					info(_s('Invalid interval "%1$s".', $interval['schedule']));
					break;
				}

				$intervals[] = $interval['schedule'];
			}
		}

		if ($intervals) {
			$delay .= ';'.implode(';', $intervals);
		}
	}

	if ($result) {
		$preprocessing = getRequest('preprocessing', []);

		foreach ($preprocessing as &$step) {
			switch ($step['type']) {
				case ZBX_PREPROC_PROMETHEUS_TO_JSON:
					$step['params'] = trim($step['params'][0]);
					break;

				case ZBX_PREPROC_XPATH:
				case ZBX_PREPROC_JSONPATH:
				case ZBX_PREPROC_VALIDATE_NOT_REGEX:
				case ZBX_PREPROC_ERROR_FIELD_JSON:
				case ZBX_PREPROC_ERROR_FIELD_XML:
				case ZBX_PREPROC_THROTTLE_TIMED_VALUE:
				case ZBX_PREPROC_SCRIPT:
					$step['params'] = $step['params'][0];
					break;

				case ZBX_PREPROC_REGSUB:
				case ZBX_PREPROC_STR_REPLACE:
					$step['params'] = implode("\n", $step['params']);
					break;

				// ZBX-16642
				case ZBX_PREPROC_CSV_TO_JSON:
					if (!array_key_exists(2, $step['params'])) {
						$step['params'][2] = ZBX_PREPROC_CSV_NO_HEADER;
					}
					$step['params'] = implode("\n", $step['params']);
					break;

				default:
					$step['params'] = '';
			}

			$step += [
				'error_handler' => ZBX_PREPROC_FAIL_DEFAULT,
				'error_handler_params' => ''
			];
		}
		unset($step);

		$newItem = [
			'itemid' => getRequest('itemid'),
			'interfaceid' => getRequest('interfaceid'),
			'name' => getRequest('name'),
			'description' => getRequest('description'),
			'key_' => getRequest('key'),
			'hostid' => getRequest('hostid'),
			'delay' => $delay,
			'status' => getRequest('status', ITEM_STATUS_DISABLED),
			'type' => getRequest('type'),
			'snmp_oid' => getRequest('snmp_oid'),
			'trapper_hosts' => getRequest('trapper_hosts'),
			'authtype' => getRequest('authtype'),
			'username' => getRequest('username'),
			'password' => getRequest('password'),
			'publickey' => getRequest('publickey'),
			'privatekey' => getRequest('privatekey'),
			'params' => getRequest('params'),
			'ipmi_sensor' => getRequest('ipmi_sensor'),
			'lifetime' => getRequest('lifetime')
		];

		if ($newItem['type'] == ITEM_TYPE_HTTPAGENT) {
			$http_item = [
				'timeout' => getRequest('timeout', DB::getDefault('items', 'timeout')),
				'url' => getRequest('url'),
				'query_fields' => getRequest('query_fields', []),
				'posts' => getRequest('posts'),
				'status_codes' => getRequest('status_codes', DB::getDefault('items', 'status_codes')),
				'follow_redirects' => (int) getRequest('follow_redirects'),
				'post_type' => (int) getRequest('post_type'),
				'http_proxy' => getRequest('http_proxy'),
				'headers' => getRequest('headers', []),
				'retrieve_mode' => (int) getRequest('retrieve_mode'),
				'request_method' => (int) getRequest('request_method'),
				'output_format' => (int) getRequest('output_format'),
				'allow_traps' => (int) getRequest('allow_traps', HTTPCHECK_ALLOW_TRAPS_OFF),
				'ssl_cert_file' => getRequest('ssl_cert_file'),
				'ssl_key_file' => getRequest('ssl_key_file'),
				'ssl_key_password' => getRequest('ssl_key_password'),
				'verify_peer' => (int) getRequest('verify_peer'),
				'verify_host' => (int) getRequest('verify_host'),
				'authtype' => getRequest('http_authtype', HTTPTEST_AUTH_NONE),
				'username' => getRequest('http_username', ''),
				'password' => getRequest('http_password', '')
			];
			$newItem = prepareItemHttpAgentFormData($http_item) + $newItem;
		}

		if ($newItem['type'] == ITEM_TYPE_JMX) {
			$newItem['jmx_endpoint'] = getRequest('jmx_endpoint', '');
		}

		if (getRequest('type') == ITEM_TYPE_DEPENDENT) {
			$newItem['master_itemid'] = getRequest('master_itemid');
		}

		// add macros; ignore empty new macros
		$lld_rule_filter = [
			'evaltype' => getRequest('evaltype'),
			'conditions' => []
		];
		$conditions = getRequest('conditions', []);
		ksort($conditions);
		$conditions = array_values($conditions);
		foreach ($conditions as $condition) {
			if (!zbx_empty($condition['macro'])) {
				$condition['macro'] = mb_strtoupper($condition['macro']);

				$lld_rule_filter['conditions'][] = $condition;
			}
		}
		if ($lld_rule_filter['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION) {
			// if only one or no conditions are left, reset the evaltype to and/or and clear the formula
			if (count($lld_rule_filter['conditions']) <= 1) {
				$lld_rule_filter['formula'] = '';
				$lld_rule_filter['evaltype'] = CONDITION_EVAL_TYPE_AND_OR;
			}
			else {
				$lld_rule_filter['formula'] = getRequest('formula');
			}
		}
		$newItem['filter'] = $lld_rule_filter;

		$lld_macro_paths = getRequest('lld_macro_paths', []);

		foreach ($lld_macro_paths as &$lld_macro_path) {
			$lld_macro_path['lld_macro'] = mb_strtoupper($lld_macro_path['lld_macro']);
		}
		unset($lld_macro_path);

		$newItem['lld_macro_paths'] = $lld_macro_paths;

		foreach ($newItem['lld_macro_paths'] as $i => $lld_macro_path) {
			if ($lld_macro_path['lld_macro'] === '' && $lld_macro_path['path'] === '') {
				unset($newItem['lld_macro_paths'][$i]);
			}
		}

		$overrides = getRequest('overrides', []);
		$newItem['overrides'] = $overrides;

		if (hasRequest('update')) {
			DBstart();

			// unset unchanged values
			$newItem = CArrayHelper::unsetEqualValues($newItem, $item, ['itemid']);

			// don't update the filter if it hasn't changed
			$conditionsChanged = false;
			if (count($newItem['filter']['conditions']) != count($item['filter']['conditions'])) {
				$conditionsChanged = true;
			}
			else {
				$conditions = $item['filter']['conditions'];
				foreach ($newItem['filter']['conditions'] as $i => $condition) {
					if (CArrayHelper::unsetEqualValues($condition, $conditions[$i])) {
						$conditionsChanged = true;
						break;
					}
				}
			}
			$lld_rule_filter = CArrayHelper::unsetEqualValues($newItem['filter'], $item['filter']);
			if (!isset($lld_rule_filter['evaltype']) && !isset($lld_rule_filter['formula']) && !$conditionsChanged) {
				unset($newItem['filter']);
			}

			$lld_macro_paths_changed = false;

			if (count($newItem['lld_macro_paths']) != count($item['lld_macro_paths'])) {
				$lld_macro_paths_changed = true;
			}
			else {
				$lld_macro_paths = array_values($item['lld_macro_paths']);
				$newItem['lld_macro_paths'] = array_values($newItem['lld_macro_paths']);

				foreach ($newItem['lld_macro_paths'] as $i => $lld_macro_path) {
					if (CArrayHelper::unsetEqualValues($lld_macro_path, $lld_macro_paths[$i])) {
						$lld_macro_paths_changed = true;
						break;
					}
				}
			}

			if (!$lld_macro_paths_changed) {
				unset($newItem['lld_macro_paths']);
			}

			if ($item['preprocessing'] !== $preprocessing) {
				$newItem['preprocessing'] = $preprocessing;
			}

			$result = API::DiscoveryRule()->update($newItem);
			$result = DBend($result);
		}
		else {
			if (!$newItem['lld_macro_paths']) {
				unset($newItem['lld_macro_paths']);
			}

			if ($preprocessing) {
				$newItem['preprocessing'] = $preprocessing;
			}

			$result = API::DiscoveryRule()->create([$newItem]);
		}
	}

	if (hasRequest('add')) {
		show_messages($result, _('Discovery rule created'), _('Cannot add discovery rule'));
	}
	else {
		show_messages($result, _('Discovery rule updated'), _('Cannot update discovery rule'));
	}

	if ($result) {
		unset($_REQUEST['itemid'], $_REQUEST['form']);
		uncheckTableRows($checkbox_hash);
	}
}
elseif (hasRequest('action') && str_in_array(getRequest('action'), ['discoveryrule.massenable', 'discoveryrule.massdisable']) && hasRequest('g_hostdruleid')) {
	$itemids = getRequest('g_hostdruleid');
	$status = (getRequest('action') === 'discoveryrule.massenable') ? ITEM_STATUS_ACTIVE : ITEM_STATUS_DISABLED;

	$lld_rules = [];
	foreach ($itemids as $itemid) {
		$lld_rules[] = ['itemid' => $itemid, 'status' => $status];
	}

	$result = (bool) API::DiscoveryRule()->update($lld_rules);

	if ($result) {
		uncheckTableRows($checkbox_hash);
	}

	$updated = count($itemids);

	$messageSuccess = ($status == ITEM_STATUS_ACTIVE)
		? _n('Discovery rule enabled', 'Discovery rules enabled', $updated)
		: _n('Discovery rule disabled', 'Discovery rules disabled', $updated);
	$messageFailed = ($status == ITEM_STATUS_ACTIVE)
		? _n('Cannot enable discovery rule', 'Cannot enable discovery rules', $updated)
		: _n('Cannot disable discovery rule', 'Cannot disable discovery rules', $updated);

	show_messages($result, $messageSuccess, $messageFailed);
}
elseif (hasRequest('action') && getRequest('action') === 'discoveryrule.massdelete' && hasRequest('g_hostdruleid')) {
	$result = API::DiscoveryRule()->delete(getRequest('g_hostdruleid'));

	if ($result) {
		uncheckTableRows($checkbox_hash);
	}
	show_messages($result, _('Discovery rules deleted'), _('Cannot delete discovery rules'));
}
elseif (hasRequest('action') && getRequest('action') === 'discoveryrule.masscheck_now' && hasRequest('g_hostdruleid')) {
	$result = (bool) API::Task()->create([
		'type' => ZBX_TM_TASK_CHECK_NOW,
		'itemids' => getRequest('g_hostdruleid')
	]);

	if ($result) {
		uncheckTableRows($checkbox_hash);
	}

	show_messages($result, _('Request sent successfully'), _('Cannot send request'));
}

if (hasRequest('action') && hasRequest('g_hostdruleid') && !$result) {
	$hostdrules = API::DiscoveryRule()->get([
		'output' => [],
		'itemids' => getRequest('g_hostdruleid'),
		'editable' => true
	]);
	uncheckTableRows($checkbox_hash, zbx_objectValues($hostdrules, 'itemid'));
}

/*
 * Display
 */
if (hasRequest('form')) {
	$has_errors = false;
	$form_item = (hasRequest('itemid') && !hasRequest('clone')) ? $item : [];
	$master_itemid = $form_item && !hasRequest('form_refresh')
		? $form_item['master_itemid']
		: getRequest('master_itemid');

	if (getRequest('type', $form_item ? $form_item['type'] : null) == ITEM_TYPE_DEPENDENT && $master_itemid != 0) {
		$db_master_items = API::Item()->get([
			'output' => ['itemid', 'type', 'hostid', 'name', 'key_'],
			'itemids' => $master_itemid,
			'webitems' => true
		]);

		if (!$db_master_items) {
			show_messages(false, '', _('No permissions to referred object or it does not exist!'));
			$has_errors = true;
		}
		else {
			$form_item['master_item'] = $db_master_items[0];
		}
	}

	$data = getItemFormData($form_item, [
		'is_discovery_rule' => true
	]);
	$data['lifetime'] = getRequest('lifetime', DB::getDefault('items', 'lifetime'));
	$data['evaltype'] = getRequest('evaltype');
	$data['formula'] = getRequest('formula');
	$data['conditions'] = getRequest('conditions', []);
	$data['lld_macro_paths'] = getRequest('lld_macro_paths', []);
	$data['overrides'] = getRequest('overrides', []);
	$data['host'] = $host;
	$data['preprocessing_test_type'] = CControllerPopupItemTestEdit::ZBX_TEST_TYPE_LLD;
	$data['preprocessing_types'] = CDiscoveryRule::$supported_preprocessing_types;

	if (!hasRequest('form_refresh')) {
		foreach ($data['preprocessing'] as &$step) {
			if ($step['type'] == ZBX_PREPROC_SCRIPT) {
				$step['params'] = [$step['params'], ''];
			}
			else {
				$step['params'] = explode("\n", $step['params']);
			}
		}
		unset($step);
	}

	// update form
	if (hasRequest('itemid') && !getRequest('form_refresh')) {
		$data['lifetime'] = $item['lifetime'];
		$data['evaltype'] = $item['filter']['evaltype'];
		$data['formula'] = $item['filter']['formula'];
		$data['conditions'] = $item['filter']['conditions'];
		$data['lld_macro_paths'] = $item['lld_macro_paths'];
		$data['overrides'] = $item['overrides'];
		// Sort overides to be listed in step order.
		CArrayHelper::sort($data['overrides'], [
			['field' => 'step', 'order' => ZBX_SORT_UP]
		]);
	}
	// clone form
	elseif (hasRequest('clone')) {
		unset($data['itemid']);
		$data['form'] = 'clone';
	}

	// Sort interfaces to be listed starting with one selected as 'main'.
	CArrayHelper::sort($data['interfaces'], [
		['field' => 'main', 'order' => ZBX_SORT_DOWN]
	]);

	if ($data['type'] != ITEM_TYPE_JMX) {
		$data['jmx_endpoint'] = ZBX_DEFAULT_JMX_ENDPOINT;
	}

	// render view
	if (!$has_errors) {
		echo (new CView('configuration.host.discovery.edit', $data))->getOutput();
	}
}
else {
	$config = select_config();

	$data = [
		'filter' => $filter,
		'hostid' => (count($filter_hostids) == 1) ? reset($filter_hostids) : 0,
		'sort' => $sort_field,
		'sortorder' => $sort_order,
		'profileIdx' => 'web.host_discovery.filter',
		'active_tab' => CProfile::get('web.host_discovery.filter.active', 1),
		'checkbox_hash' => $checkbox_hash
	];

	// Select LLD rules.
	$options = [
		'output' => API_OUTPUT_EXTEND,
		'selectHosts' => ['hostid', 'name'],
		'selectItems' => API_OUTPUT_COUNT,
		'selectGraphs' => API_OUTPUT_COUNT,
		'selectTriggers' => API_OUTPUT_COUNT,
		'selectHostPrototypes' => API_OUTPUT_COUNT,
		'editable' => true,
		'filter' => [],
		'search' => [],
		'sortfield' => $sort_field,
		'limit' => $config['search_limit'] + 1
	];

	if ($filter_groupids) {
		$options['groupids'] = $filter_groupids;
	}

	if ($filter_hostids) {
		$options['hostids'] = $filter_hostids;
	}

	if ($filter['name'] !== '') {
		$options['search']['name'] = $filter['name'];
	}

	if ($filter['key'] !== '') {
		$options['search']['key_'] = $filter['key'];
	}

	if ($filter['type'] != -1) {
		$options['filter']['type'] = $filter['type'];
	}

	/*
	 * Trapper and SNMP trap items contain zeros in "delay" field and, if no specific type is set, look in item types
	 * other than trapper and SNMP trap that allow zeros. For example, when a flexible interval is used. Since trapper
	 * and SNMP trap items contain zeros, but those zeros should not be displayed, they cannot be filtered by entering
	 * either zero or any other number in filter field.
	 */
	if ($filter['delay'] !== '') {
		if ($filter['type'] == -1 && $filter['delay'] == 0) {
			$options['filter']['type'] = [ITEM_TYPE_NETAFIER, ITEM_TYPE_SIMPLE,  ITEM_TYPE_INTERNAL,
				ITEM_TYPE_NETAFIER_ACTIVE, ITEM_TYPE_EXTERNAL, ITEM_TYPE_DB_MONITOR, ITEM_TYPE_IPMI,
				ITEM_TYPE_SSH, ITEM_TYPE_TELNET, ITEM_TYPE_JMX
			];
			$options['filter']['delay'] = $filter['delay'];
		}
		elseif ($filter['type'] == ITEM_TYPE_TRAPPER || $filter['type'] == ITEM_TYPE_DEPENDENT) {
			$options['filter']['delay'] = -1;
		}
		else {
			$options['filter']['delay'] = $filter['delay'];
		}
	}

	if ($filter['lifetime'] !== '') {
		$options['filter']['lifetime'] = $filter['lifetime'];
	}

	if ($filter['snmp_oid'] !== '') {
		$options['filter']['snmp_oid'] = $filter['snmp_oid'];
	}

	if ($filter['status'] != -1) {
		$options['filter']['status'] = $filter['status'];
	}

	if ($filter['state'] != -1) {
		$options['filter']['status'] = ITEM_STATUS_ACTIVE;
		$options['filter']['state'] = $filter['state'];
	}

	$data['discoveries'] = API::DiscoveryRule()->get($options);
	$data['discoveries'] = CMacrosResolverHelper::resolveItemNames($data['discoveries']);

	switch ($sort_field) {
		case 'delay':
			orderItemsByDelay($data['discoveries'], $sort_order, ['usermacros' => true]);
			break;

		case 'status':
			orderItemsByStatus($data['discoveries'], $sort_order);
			break;

		default:
			order_result($data['discoveries'], $sort_field, $sort_order);
	}

	$data['discoveries'] = expandItemNamesWithMasterItems($data['discoveries'], 'items');

	// pager
	if (hasRequest('page')) {
		$page_num = getRequest('page');
	}
	elseif (isRequestMethod('get') && !hasRequest('cancel')) {
		$page_num = 1;
	}
	else {
		$page_num = CPagerHelper::loadPage($page['file']);
	}

	CPagerHelper::savePage($page['file'], $page_num);

	$data['paging'] = CPagerHelper::paginate($page_num, $data['discoveries'], $sort_order,
		new CUrl('host_discovery.php')
	);

	$data['parent_templates'] = getItemParentTemplates($data['discoveries'], ZBX_FLAG_DISCOVERY_RULE);

	// render view
	echo (new CView('configuration.host.discovery.list', $data))->getOutput();
}

require_once dirname(__FILE__).'/include/page_footer.php';
