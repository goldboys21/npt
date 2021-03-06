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

class __create extends xmd {
	public function __construct() {
		parent::__construct();
		
		$this->_m(_array_keys(w('news event artist people')));
		
		$this->load('objects');
	}
	
	public function home() {
		// TODO: List methods
		_fatal();
	}
	
	public function news() {
		return $this->method();
	}
	
	protected function _news_home() {
		if (_button()) {
			global $bio;
			
			$v = $this->__(w('subject excerpt content'));
			
			if (!f($v['subject']) || !f($v['content'])) {
				$this->_error('#EMPTY_FIELDS');
			}
			
			$v['alias'] = _alias($v['subject']);
			$v['approved'] = (int) $bio->v('auth_news_approve');
			
			$sql = 'SELECT news_id
				FROM _news
				WHERE news_alias = ?';
			if (_fieldrow(sql_filter($sql, $v['alias']))) {
				$this->_error('ALIAS_EXISTS');
			}
			
			$add = array(
				'uid' =>$bio->v('bio_id'),
				'time' => time()
			);
			
			$this->objects->merge($v, (object) $add);
			$v = $this->objects->all();
			
			sql_put('_news', prefix('news', $v));
			
			redirect(_link('news', array('x1' => 'read', 'i' => $v->alias)));
		}
		
		return;
	}
	
	public function event() {
		return $this->method();
	}
	
	protected function _event_home() {
		global $warning;
		
		$v = $this->__(array('e_title', 'e_text', 'e_time' => array(0), 'e_artists' => array(0)));
		
		$v_check = array('e_title' => 'INVALID_NAME', 'e_cat' => 'INVALID_CATEGORY');
		foreach ($v_check as $vk => $vv) {
			if (empty($v->$vk)) $warning->set($vv);
		}
		
		if (!$warning->exist) {
			$v['e_alias'] = _alias($v['e_title']);
			
			if (empty($v['e_alias'])) {
				$arning->set('INVALID_ALIAS');
			}
		}
		
		if (!$warning->exist) {
			$sql = 'SELECT cat_id
				FROM _events_category
				WHERE cat_id = ?';
			if (!sql_fieldrow(sql_filter($sql, $v->e_cat))) {
				$this->warning->set('invalid_category');
			}
		}
		
		if (!$this->warning->exist) {
			$core->require('upload');
			$core->upload->init();
			
			$f = $core->upload->process(LIB . 'tmp/', $_FILES['e_flyer'], w('jpg'), max_upload_size());
			
			if ($f === false && count($core->upload->warning)) {
				$this->warning->set($core->upload->warning);
			}
		}
		
		if (!$this->warning->exist()) {
			$sql_insert = array(
				'alias' => $v['e_alias'],
				'subject' => str_normalize($v['e_title']),
				'text' => str_normalize($v['e_text']),
				'approved' => 0,
				'views' => 0,
				'posts' => 0,
				'start' => $e_start,
				'end' => $e_end,
				'images' => 0
			);
			sql_put('_events', prefix('event', $sql_insert));
			$v['e_id'] = sql_nextid();
			
			if (is_array($v->e_artists)) {
				foreach ($v['e_artists'] as $row) {
					$sql_insert = array(
						'id' => (int) $v['e_id'],
						'artist' => (int) $row
					);
					sql_put('_events_artists', prefix('event', $sql_insert));
				}
			}
			
			foreach ($f as $row) {
				$f2 = $upload->resize($row, LIB . 'tmp', LIB . 'events/future/', $v['e_id'], array(600, 400), false, false, true);
				if ($f2 === false) continue;
				
				$f3 = $upload->resize($row, LIB . 'events/future/', LIB . 'events/preview/', $v['e_id'], array(210, 210), false, false);
			}
			
			redirect(_link('events', $v['e_alias']));
		}
		return;
	}
	
	public function artist() {
		return $this->method();
	}
	
	protected function _artist_home() {
		$v = array_merge($v, $this->__(array('a_name', 'a_website', 'a_email', 'a_genre' => array(0), 'a_country' => 0)));
		
		$v_check = array('a_name' => 'INVALID_NAME', 'a_email' => 'INVALID_EMAIL', 'a_genre' => 'INVALID_GENRE');
		foreach ($v_check as $vk => $vv) {
			if (!f($v[$vk])) $this->error($vv);
		}
		
		if (!$this->errors()) {
			$v['a_alias'] = _alias($v['a_name']);
			
			if (f($v['a_alias'])) {
				$sql = 'SELECT a_approved
					FROM _artists
					WHERE a_alias = ?';
				if ($a_approved = _field(sql_filter($sql, $v['a_alias']), 'a_approved')) {
					$a_msg = ($a_approved) ? 'EXISTS' : 'PENDING';
					$this->error('ARTIST_' . $a_msg);
				}
			} else {
				$this->error('INVALID_ALIAS');
			}
		}
		
		if (!$this->errors() && !check_email($v['a_email'])) {
			$this->error('INVALID_EMAIL');
		}
		
		if (!$this->errors()) {
			$sql = 'SELECT country_id
				FROM _countries
				WHERE country_id = ?';
			if (!_fieldrow(sql_filter($sql, $v['a_country']))) {
				$this->error('INVALID_COUNTRY');
			}
		}
		
		if (!$this->errors()) {
			$sql = 'SELECT type_id
				FROM _alias_type
				WHERE type_alias = ?';
			$alias_type = _field(sql_filter($sql, 'artist'), 'type_id');
			
			$sql_insert = array(
				'name' => $v['a_name'],
				'alias' => $v['a_alias'],
				'approved' => 0,
				'time' => time(),
				'email' => strtolower($v['a_email']),
				'website' => $v['a_website'],
				'country' => $v['a_country'],
				'biography' => '',
				'views' => 0,
				'music' => 0,
				'video' => 0,
				'news' => 0,
				'posts' => 0,
				'votes' => 0,
				'lyrics' => 0,
				'images' => 0
			);
			sql_put('_artists', prefix('a', $sql_insert));
			
			$sql_insert = array(
				'name' => $v['a_alias'],
				'enable' => 0,
				'type' => $alias_type
			);
			sql_put('_alias', prefix('alias', $sql_insert));
			
			redirect(_link('alias', array('alias' => $v['a_alias'])));
		}
		return;
	}
	
	public function people() {
		return $this->method();
	}
	
	protected function _people_home() {
		return;
	}
}

?>