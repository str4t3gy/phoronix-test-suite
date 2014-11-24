<?php

/*
	Phoronix Test Suite
	URLs: http://www.phoronix.com, http://www.phoronix-test-suite.com/
	Copyright (C) 2014, Phoronix Media
	Copyright (C) 2014, Michael Larabel
	pts-web-socket: A simple WebSocket implementation, inspired by designs of https://github.com/varspool/Wrench and http://code.google.com/p/phpwebsocket/

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; either version 3 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

class pts_phoromatic_event_server
{
	public function __construct()
	{
		phoromatic_server::prepare_database();

		while(true)
		{
			$hour = date('G');
			$minute = date('i');

			if($minute == 0)
			{
				$stmt = phoromatic_server::$db->prepare('SELECT LastCommunication, CurrentTask, SystemID, AccountID, LastIP FROM phoromatic_systems WHERE State > 0 ORDER BY LastCommunication DESC');
				$result = $stmt->execute();

				while($row = $result->fetchArray())
				{
					$last_comm = strtotime($row['LastCommunication']);

					if($last_comm > (time() - 3600))
						continue; // if last comm time is less than an hour, still might be busy testing

					if($last_comm < (time() - (3600 * 3)))
						break; // it's already been reported enough for now...

					if(stripos($row['CurrentTask'], 'shutdown') !== false || stripos($row['CurrentTask'], 'shutting down') !== false)
						continue; // if the system shutdown, no reason to report it

					$stmt_email = phoromatic_server::$db->prepare('SELECT UserName, Email FROM phoromatic_users WHERE UserID IN (SELECT UserID FROM phoromatic_user_settings WHERE AccountID = :account_id AND NotifyOnHungSystems = 1) AND AccountID = :account_id');
					$stmt_email->bindValue(':account_id', $row['AccountID']);
					$result_email = $stmt_email->execute();
					while($row_email = $result_email->fetchArray())
					{
						if(empty($row_email['Email']))
							continue;

						phoromatic_server::send_email($row_email['Email'], 'Phoromatic System Potential Hang: ' . phoromatic_server::system_id_to_name($row['SystemID'], $row['AccountID']), 'no-reply@phoromatic.com', '<p><strong>' . $row_email['UserName'] . ':</strong></p><p>One of the systems associated with your Phoromatic account has not been communicating with the Phoromatic Server in more than sixty minutes. Below is the system information details:</p><p><strong>System:</strong> ' . phoromatic_server::system_id_to_name($row['SystemID'], $row['AccountID']) . '<br /><strong>Last Communication:</strong> ' . $row['LastCommunication'] . '<br /><strong>Last Task:</strong> ' . $row['CurrentTask'] . '<br /><strong>Local IP:</strong> ' . $row['LastIP'] . '</p>');
					}
				}
			}

			sleep((60 - date('s') + 1));
		}
	}
}

?>