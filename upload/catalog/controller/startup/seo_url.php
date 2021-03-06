<?php
class ControllerStartupSeoUrl extends Controller {
	public function index() {
		// Add rewrite to url class
		if ($this->config->get('config_seo_url')) {
			$this->url->addRewrite($this);
		}

		// Decode URL
		if (isset($this->request->get['_route_'])) {
			$parts = explode('/', $this->request->get['_route_']);

			// remove any empty arrays from trailing
			if (utf8_strlen(end($parts)) == 0) {
				array_pop($parts);
			}

			foreach ($parts as $part) {
				$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "seo_url WHERE keyword = '" . $this->db->escape($part) . "' AND store_id = '" . (int)$this->config->get('config_store_id') . "'");

				if ($query->num_rows) {
					parse_str(html_entity_decode($query->row['push'], ENT_QUOTES, 'UTF-8'), $data);

					foreach ($data as $key => $value) {
						$this->request->get[$key] = $value;
					}
				} else {
					$this->request->get['route'] = 'error/not_found';

					break;
				}
			}
		}
	}

	public function rewrite($link) {
		$url = '';

		$url_info = parse_url(str_replace('&amp;', '&', $link));

		parse_str($url_info['query'], $data);

		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "seo_regex WHERE '" . $this->db->escape($url_info['query']) . "' REGEXP `regex` ORDER BY sort_order ASC");

		foreach ($query->rows as $result) {
			if (preg_match('/' . $result['regex'] . '/', $url_info['query'], $matches)) {
				array_shift($matches);

				foreach ($matches as $match) {
					$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "seo_url WHERE `query` = '" . $this->db->escape($match) . "' AND keyword != '' AND store_id = '" . (int)$this->config->get('config_store_id') . "' AND language_id = '" . (int)$this->config->get('config_language_id') . "'");

					foreach ($query->rows as $part) {
						$url .= '/' . $part['keyword'];
					}

					$key = substr($match, 0, strpos($match, '='));

					if (isset($data[$key])) {
						unset($data[$key]);
					}
				}
			}
		}

		if ($url) {
			$query = '';

			if ($data) {
				foreach ($data as $key => $value) {
					$query .= '&' . rawurlencode((string)$key) . '=' . rawurlencode(is_array($value) ? http_build_query($value) : (string)$value);
				}

				if ($query) {
					$query = '?' . str_replace('&', '&amp;', trim($query, '&'));
				}
			}

			return $url_info['scheme'] . '://' . $url_info['host'] . (isset($url_info['port']) ? ':' . $url_info['port'] : '') . str_replace('/index.php', '', $url_info['path']) . $url . $query;
		} else {
			return $link;
		}
	}
}