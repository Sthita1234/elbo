<?php

namespace Elbo\RateLimiters;

use Elbo\Library\RateLimiter;

abstract class IPRateLimiter extends RateLimiter {
	protected function getKey(string $ip) {
		$key = bin2hex(@inet_pton($ip));

		if ($key === false) {
			throw new \InvalidArgumentException("Invalid IP address");
		}

		if (strlen($key) === 8) { // IPv4
			return "{$this->redis_key_prefix}4_$key";
		}
		else { // IPv6 /80 subnet
			return "{$this->redis_key_prefix}6_".substr($key, 0, 20);
		}
	}
}
