<?php
/*
<NPT, a web development framework.>
Copyright (C) <2009>  <NPT>

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/
if (!defined('XFS')) exit;

/*
 * if email and key exists then login
 * if email and not key then key recovery
 * if not email and not key then new account
 */

interface i_sign {
	public function home();
	public function in();
	public function out();
	public function up();
	public function ed();
	public function en();
}

class __sign extends xmd implements i_sign {
	public function __construct() {
		parent::__construct();
		
		$this->auth(false);
		$this->_m(_array_keys(w('up ed en in out')));
	}
	
	public function home() {
		_fatal();
	}
	
	public function in() {
		return $this->method();
	}
	
	protected function _in_home() {
		global $bio, $core, $warning;
		
		if (!_button()) {
			return;
		}
		
		$v = $this->__(w('page address key'));
		
		if ($bio->v('auth_member')) {
			redirect($v->page);
		}
		
		if (empty($v->address)) {
			$warning->set('LOGIN_ERROR');
		}
		
		if (_button('recovery')) {
			$sql = 'SELECT bio_id, bio_name, bio_address, bio_recovery
				FROM _bio
				WHERE bio_address = ?
					AND bio_id <> ?
					AND bio_id NOT IN (
						SELECT ban_userid
						FROM _banlist
					)';
			if ($recovery = sql_fieldrow(sql_filter($sql, $v->address, 1))) {
				$email = array(
					'USERNAME' => $recovery->bio_name,
					'U_RECOVERY' => _link('my', array('recovery', 'k' => _rainbow_create($recovery->bio_id))),
					'U_PROFILE' => _link('-', $recovery->bio_nickname)
				);
				
				$core->email->init('info', 'bio_recovery', $email);
				$core->email->send($recovery->bio_address);
				
				$sql = 'UPDATE _bio SET bio_recovery = bio_recovery + 1
					WHERE bio_id = ?';
				sql_query(sql_filter($sql, $recovery->bio_id));
			}
			
			$this->_stop('RECOVERY_LEGEND');
		}

		if (empty($v->key)) {
			$warning->set('login_fail');
		}

		$v->register = false;
		$v->field = (email_format($v->address)) ? 'address' : 'name';
		
		$sql = 'SELECT address_bio
			FROM _bio_address
			WHERE address_name = ?';
		if ($bio_address = sql_field(sql_filter($sql, $v->address), 'address_bio', 0)) {
			$sql = 'SELECT bio_id, bio_key, bio_fails
				FROM _bio
				WHERE bio_id = ?
					AND bio_active = ?';
			if ($_bio = sql_fieldrow(sql_filter($sql, $bio_address, 1))) {
				if (ValidatePassword($v->key, $_bio->bio_key)) {
					if ($_bio->bio_fails) {
						$sql = 'UPDATE _bio SET bio_fails = 0
							WHERE bio_id = ?';
						sql_query(sql_filter($sql, $_bio->bio_id));
					}
					
					$bio->session_create($_bio->bio_id);
					redirect($v->page);
				}

				if ($_bio->bio_fails == $core->v('account_failcount')) {
					// TODO: Captcha system if failcount reached
					// TODO: Notification about blocked account
					_fatal(508);
				}
				
				$sql = 'UPDATE _bio SET bio_fails = bio_fails + 1
					WHERE bio_id = ?';
				sql_query(sql_filter($sql, $_bio->bio_id));
				
				sleep(5);
				
				for ($i = 1; $i < 32; $i++) {
					if ($i == 1) _style('birth_day');
					
					_style('birth_day.row', array(
						'DAY' => $i)
					);
				}
				
				for ($i = 1; $i < 13; $i++) {
					if ($i == 1) _style('birth_month');
					
					_style('birth_month.row', array(
						'MONTH' => $i)
					);
				}
				
				for ($i = date('Y'); $i > 1900; $i--) {
					if ($i == date('Y')) _style('birth_year');
					
					_style('birth_year.row', array(
						'YEAR' => $i)
					);
				}
				
				_style('error', array(
					'MESSAGE' => 'Los datos ingresados son inv&aacute;lidos, por favor intenta nuevamente.')
				);
				
				return;
			}
		} else {
			$v->register = true;
		}
		
		if ($v->register) {
			$this->_up_home();
		}
		
		return;
	}
	
	public function out() {
		return $this->method();
	}
	
	protected function _out_home() {
		global $bio;
		
		//if ($bio->v('is_bio')) {
			$bio->session_kill();
			
			$bio->v('is_bio', false);
			$bio->v('session_page', '');
			$bio->v('session_time', time());
		//}
		
		redirect(_link());
	}
	
	public function en() {
		return $this->method();
	}
	
	protected function _en_home() {
		return;
	}
	
	public function up() {
		return $this->method();
	}
	
	protected function _up_home() {
		global $bio, $warning;
		
		$v = $this->__(w('send address'));
		
		if (!empty($v->send)) {
			$v = _array_merge($v, $this->__(array_merge(w('password firstname lastname country status'), _array_keys(w('gender birth_day birth_month birth_year'), 0))));
			
			if (empty($v->address)) {
				$warning->set('empty_address');
			}
			
			if (empty($v->password)) {
				$warning->set('empty_password');
			}

			if (!email_format($v->address)) {
				$warning->set('bad_address');
			}
			
			if (!$v->alias = _low($v->firstname . $v->lastname)) {
				$warning->set('bad_alias');
			}
			
			if ($this->alias_exists($v->alias)) {
				$warning->set('record_alias');
			}
			
			if (!$v->country = $this->country_exists($v->country)) {
				$warning->set('bad_country');
			}
			
			if (!$v->birth_day || !$v->birth_month || !$v->birth_year) {
				$warning->set('bad_birth');
			}
			
			$v->birth = _timestamp($v->birth_month, $v->birth_day, $v->birth_year);
			$v->name = trim($v->firstname) . ' ' . trim($v->lastname);
			
			$sql_insert = array(
				'type' => 0,
				'level' => 0,
				'active' => 1,
				'alias' => $v->alias,
				'name' => $v->firstname . ' ' . $v->lastname,
				'first' => $v->firstname,
				'last' => $v->lastname,
				'key' => HashPassword($v->password),
				'address' => $v->address,
				'gender' => $v->gender,
				'birth' => $v->birth,
				'birthlast' => 0,
				'regip' => $bio->v('ip'),
				'regdate' => time(),
				'session_time' => time(),
				'lastpage' => '',
				'timezone' => -6,
				'dst' => 0,
				'dateformat' => 'd M Y H:i',
				'lang' => 'sp',
				'country' => $v->country,
				'avatar' => '',
				'actkey' => '',
				'recovery' => 0,
				'fails' => 0
			);
			$bio->id = sql_put('_bio', prefix('bio', $sql_insert));
			
			$sql_insert = array(
				'bio' => $bio->id,
				'name' => $v->address,
				'primary' => 1
			);
			sql_put('_bio_address', prefix('address', $sql_insert));
			
			echo 'OK';
			exit;
		}
		
		//$gi = geoip_open(XFS.XCOR . 'store/geoip.dat', GEOIP_STANDARD);
		
		$geoip_code = '';
		if ($bio->v('ip') != '127.0.0.1') {
			// GeoIP
			if (!@function_exists('geoip_country_code_by_name')) {
				//require_once(XFS.XCOR . 'geoip.php');
			}
			
			//$geoip_code = @geoip_country_code_by_name($bio->v('ip'));
		}
		
		for ($i = 1; $i < 32; $i++) {
			if ($i == 1) _style('birth_day');
			
			_style('birth_day.row', array(
				'DAY' => $i)
			);
		}
		
		for ($i = 1; $i < 13; $i++) {
			if ($i == 1) _style('birth_month');
			
			_style('birth_month.row', array(
				'MONTH' => $i)
			);
		}
		
		for ($i = date('Y'); $i > 1900; $i--) {
			if ($i == date('Y')) _style('birth_year');
			
			_style('birth_year.row', array(
				'YEAR' => $i)
			);
		}
		
		//_pre($geoip_code, true);
		
		/*
		$sql = 'SELECT *
			FROM _countries
			ORDER BY country_name';
		$countries = sql_rowset($sql);
		
		$v->country = ($v->country) ? $v->country : ((isset($country_codes[$geoip_code])) ? $country_codes[$geoip_code] : $country_codes['gt']);
		
		foreach ($countries as $i => $row) {
			if (!$i) _style('countries');
			
			_style('countries.row', array(
				'V_ID' => $row->country_id,
				'V_NAME' => $row->country_name,
				'V_SEL' => 0)
			);
		}
		 * 
		 */
		
		return;
	}
	
	public function ed() {
		return $this->method();
	}
	
	protected function _ed_home() {
		global $bio;
		
		$v = $this->__(w('k'));
		
		if (empty($v->k) || (!$rainbow = _rainbow_check($v->k))) {
			_fatal();
		}
		
		$sql = 'UPDATE _bio SET bio_active = 1
			WHERE bio_id = ?';
		sql_query(sql_filter($sql, $rainbow->rainbow_uid));
		
		_rainbow_remove($rainbow->rainbow_code);
		
		if (!$bio->v('auth_member')) {
			$bio->session_create($rainbow->rainbow_uid);
		}
		
		return redirect(_link('-', $bio->v('bio_alias')));
	}
}

?>