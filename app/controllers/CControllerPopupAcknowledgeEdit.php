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


class CControllerPopupAcknowledgeEdit extends CController {

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = [
			'eventids' =>				'required|array_db acknowledges.eventid',
			'message' =>				'db acknowledges.message|flags '.P_CRLF,
			'scope' =>					'in '.ZBX_ACKNOWLEDGE_SELECTED.','.ZBX_ACKNOWLEDGE_PROBLEM,
			'change_severity' =>		'db acknowledges.action|in '.ZBX_PROBLEM_UPDATE_NONE.','.ZBX_PROBLEM_UPDATE_SEVERITY,
			'severity' =>				'ge '.TRIGGER_SEVERITY_NOT_CLASSIFIED.'|le '.TRIGGER_SEVERITY_COUNT,
			'acknowledge_problem' =>	'db acknowledges.action|in '.ZBX_PROBLEM_UPDATE_NONE.','.ZBX_PROBLEM_UPDATE_ACKNOWLEDGE,
			'unacknowledge_problem' =>	'db acknowledges.action|in '.ZBX_PROBLEM_UPDATE_NONE.','.ZBX_PROBLEM_UPDATE_UNACKNOWLEDGE,
			'close_problem' =>			'db acknowledges.action|in '.ZBX_PROBLEM_UPDATE_NONE.','.ZBX_PROBLEM_UPDATE_CLOSE
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$output = [];

			if (($messages = getMessages()) !== null) {
				$output['errors'] = $messages->toString();
			}

			$this->setResponse(
				(new CControllerResponseData(['main_block' => json_encode($output)]))->disableView()
			);
		}

		return $ret;
	}

	protected function checkPermissions() {
		$events = API::Event()->get([
			'countOutput' => true,
			'eventids' => $this->getInput('eventids'),
			'source' => EVENT_SOURCE_TRIGGERS,
			'object' => EVENT_OBJECT_TRIGGER
		]);

		return ($events == count($this->getInput('eventids')));
	}

	protected function doAction() {
		$data = [
			'sid' => $this->getUserSID(),
			'eventids' => $this->getInput('eventids'),
			'message' => $this->getInput('message', ''),
			'scope' => (int) $this->getInput('scope', ZBX_ACKNOWLEDGE_SELECTED),
			'change_severity' => $this->getInput('change_severity', ZBX_PROBLEM_UPDATE_NONE),
			'severity' => $this->hasInput('severity') ? (int) $this->getInput('severity') : null,
			'acknowledge_problem' => $this->getInput('acknowledge_problem', ZBX_PROBLEM_UPDATE_NONE),
			'unacknowledge_problem' => $this->getInput('unacknowledge_problem', ZBX_PROBLEM_UPDATE_NONE),
			'close_problem' => $this->getInput('close_problem', ZBX_PROBLEM_UPDATE_NONE),
			'related_problems_count' => 0,
			'problem_can_be_closed' => false,
			'problem_severity_can_be_changed' => false
		];

		// Select events.
		$events = API::Event()->get([
			'output' => ['eventid', 'name', 'objectid', 'acknowledged', 'value', 'r_eventid'],
			'select_acknowledges' => ['userid', 'clock', 'message', 'action', 'old_severity', 'new_severity'],
			'eventids' => $this->getInput('eventids'),
			'source' => EVENT_SOURCE_TRIGGERS,
			'object' => EVENT_OBJECT_TRIGGER,
			'preservekeys' => true
		]);

		// Show action list if only one event is requested.
		if (count($events) == 1) {
			$config = select_config();
			$data['config'] = [
				'severity_name_0' => $config['severity_name_0'],
				'severity_name_1' => $config['severity_name_1'],
				'severity_name_2' => $config['severity_name_2'],
				'severity_name_3' => $config['severity_name_3'],
				'severity_name_4' => $config['severity_name_4'],
				'severity_name_5' => $config['severity_name_5']
			];
			$history = getEventUpdates(reset($events));
			$data['history'] = $history['data'];
			$data['users'] = API::User()->get([
				'output' => ['alias', 'name', 'surname'],
				'userids' => array_keys($history['userids']),
				'preservekeys' => true
			]);
			$data['problem_name'] = reset($events)['name'];
		}
		else {
			$data['problem_name'] = _s('%1$d problems selected.', count($events));
		}

		$triggerids = array_column($events, 'objectid', 'objectid');

		$editable_triggers = API::Trigger()->get([
			'output' => ['manual_close'],
			'triggerids' => $triggerids,
			'editable' => true,
			'preservekeys' => true
		]);

		$ack_count = 0;

		// Loop through events to figure out what operations should be allowed.
		foreach ($events as $event) {
			$can_be_closed = true;

			// Problems already resolved are not allowed to be closed.
			if ($event['r_eventid'] != 0 || $event['value'] == TRIGGER_VALUE_FALSE) {
				$can_be_closed = false;
				$data['related_problems_count']++; // Count selected but closed events.
			}
			// Not allowed to close events generated by non-writtable and non-closable triggers.
			elseif (!array_key_exists($event['objectid'], $editable_triggers)
					|| $editable_triggers[$event['objectid']]['manual_close'] == ZBX_TRIGGER_MANUAL_CLOSE_NOT_ALLOWED) {
				$can_be_closed = false;
			}
			// Look if problem is not currently in closing state due acknowledge actions.
			elseif ($event['acknowledges']) {
				foreach ($event['acknowledges'] as $acknowledge) {
					if (($acknowledge['action'] & ZBX_PROBLEM_UPDATE_CLOSE) == ZBX_PROBLEM_UPDATE_CLOSE) {
						$can_be_closed = false;
						break;
					}
				}
			}

			// If at least one event can be closed, enable 'Close problem' checkbox.
			if ($can_be_closed) {
				$data['problem_can_be_closed'] = true;
			}

			$ack_count += ($event['acknowledged'] == EVENT_ACKNOWLEDGED) ? 1 : 0;
		}

		$data['has_ack_events'] = ($ack_count > 0);
		$data['has_unack_events'] = ($ack_count != count($events));

		// Severity can be changed only for editable triggers.
		$data['problem_severity_can_be_changed'] = !!$editable_triggers;

		// Add number of selected and related problem events to count of selected resolved events.
		$data['related_problems_count'] += API::Problem()->get([
			'countOutput' => true,
			'objectids' => $triggerids
		]);

		$output = [
			'title' => _('Update problem'),
			'errors' => hasErrorMesssages() ? getMessages() : null,
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		] + $data;

		$this->setResponse(new CControllerResponseData($output));
	}
}
