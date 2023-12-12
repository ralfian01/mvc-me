<?php

namespace MVCME\URI;

use InvalidArgumentException;

/**
 * Abstraction for a uniform resource identifier (URI)
 */
class URI
{
    /**
     * Sub-delimiters used in query strings and fragments.
     */
    public const CHAR_SUB_DELIMS = '!\$&\'\(\)\*\+,;=';

    /**
     * Unreserved characters used in paths, query strings, and fragments.
     */
    public const CHAR_UNRESERVED = 'a-zA-Z0-9_\-\.~';

    /**
     * List of URI segments
     * Starts at 0
     * @var array
     */
    protected $segments = [];

    /**
     * The URI Scheme
     * @var string
     */
    protected $scheme = 'http';

    /**
     * URI User Info
     * @var string
     */
    protected $user;

    /**
     * URI User Password
     * @var string
     */
    protected $password;

    /**
     * URI Host
     * @var string
     */
    protected $host;

    /**
     * URI Port
     * @var int
     */
    protected $port;

    /**
     * URI path
     * @var string
     */
    protected $path;

    /**
     * The name of any fragment
     * @var string
     */
    protected $fragment = '';

    /**
     * The query string.
     * @var array
     */
    protected $query = [];

    /**
     * Default schemes/ports
     * @var array
     */
    protected $defaultPorts = [
        'http'  => 80,
        'https' => 443,
        'ftp'   => 21,
        'sftp'  => 22,
    ];

    /**
     * Whether passwords should be shown in userInfo/authority calls.
     * Default to false because URIs often show up in logs
     * @var bool
     */
    protected $showPassword = false;

    /**
     * If true, will use raw query string
     * @var bool
     */
    protected $rawQueryString = false;

    /**
     * Builds a representation of the string from the component parts
     * @param string|null $scheme URI scheme. E.g., http, ftp
     * @return string URI string with only passed parts. Maybe incomplete as a URI.
     */
    public static function createURIString(?string $scheme = null, ?string $authority = null, ?string $path = null, ?string $query = null, ?string $fragment = null)
    {
        $uri = '';
        if (!empty($scheme)) {
            $uri .= $scheme . '://';
        }

        if (!empty($authority)) {
            $uri .= $authority;
        }

        if (isset($path) && $path !== '') {
            $uri .= substr($uri, -1, 1) !== '/'
                ? '/' . ltrim($path, '/')
                : ltrim($path, '/');
        }

        if ($query !== '' && $query !== null) {
            $uri .= '?' . $query;
        }

        if ($fragment !== '' && $fragment !== null) {
            $uri .= '#' . $fragment;
        }

        return $uri;
    }

    /**
     * Used when resolving and merging paths to correctly interpret and remove single and double dot segments from the path per
     * @internal
     */
    public static function removeDotSegments(string $path)
    {
        if ($path === '' || $path === '/') {
            return $path;
        }

        $output = [];

        $input = explode('/', $path);

        if ($input[0] === '') {
            unset($input[0]);
            $input = array_values($input);
        }

        foreach ($input as $segment) {
            if ($segment === '..') {
                array_pop($output);
            } elseif ($segment !== '.' && $segment !== '') {
                $output[] = $segment;
            }
        }

        $output = implode('/', $output);
        $output = trim($output, '/ ');

        // Add leading slash if necessary
        if (strpos($path, '/') === 0) {
            $output = '/' . $output;
        }

        // Add trailing slash if necessary
        if ($output !== '/' && substr($path, -1, 1) === '/') {
            $output .= '/';
        }

        return $output;
    }

    /**
     * Constructor.
     * @param string|null $uri The URI to parse.
     */
    public function __construct(?string $uri = null)
    {
        if ($uri !== null) $this->setURI($uri);
    }

    /**
     * If $raw == true, then will use parseStr() method
     * instead of native parse_str() function
     * @return URI
     */
    public function useRawQueryString(bool $raw = true)
    {
        $this->rawQueryString = $raw;
        return $this;
    }

    /**
     * Sets and overwrites any current URI information
     * @return self
     */
    private function setURI(?string $uri = null)
    {
        if ($uri !== null) {
            $parts = parse_url($uri);

            $this->applyParts($parts);
        }
        return $this;
    }

    /**
     * Retrieve the scheme component of the URI
     * @return string The URI scheme
     */
    public function getScheme()
    {
        return $this->scheme;
    }

    /**
     * Retrieve the authority component of the URI
     * @return string The URI authority, in "[user-info@]host[:port]" format.
     */
    public function getAuthority(bool $ignorePort = false)
    {
        if (empty($this->host)) {
            return '';
        }

        $authority = $this->host;

        if (!empty($this->getUserInfo())) {
            $authority = $this->getUserInfo() . '@' . $authority;
        }

        // Don't add port if it's a standard port for
        // this scheme
        if (!empty($this->port) && !$ignorePort && $this->port !== $this->defaultPorts[$this->scheme]) {
            $authority .= ':' . $this->port;
        }

        $this->showPassword = false;

        return $authority;
    }

    /**
     * Retrieve the user information component of the URI     *
     * @return string|null The URI user information, in "username[:password]" format.
     */
    public function getUserInfo()
    {
        $userInfo = $this->user;

        if ($this->showPassword === true && !empty($this->password)) {
            $userInfo .= ':' . $this->password;
        }

        return $userInfo;
    }

    /**
     * Temporarily sets the URI to show a password in userInfo. Will reset itself after the first call to authority()
     * @return self
     */
    public function showPassword(bool $val = true)
    {
        $this->showPassword = $val;
        return $this;
    }

    /**
     * Retrieve the host component of the URI
     * @return string The URI host
     */
    public function getHost()
    {
        return $this->host ?? '';
    }

    /**
     * Retrieve the port component of the URI
     * @return int|null The URI port.
     */
    public function getPort()
    {
        return $this->port;
    }

    /**
     * Retrieve the path component of the URI
     * @return string The URI path.
     */
    public function getPath()
    {
        return $this->path ?? '';
    }

    /**
     * Retrieve the query string
     */
    public function getQuery(array $options = [])
    {
        $vars = $this->query;

        if (array_key_exists('except', $options)) {
            if (!is_array($options['except'])) {
                $options['except'] = [$options['except']];
            }

            foreach ($options['except'] as $var) {
                unset($vars[$var]);
            }
        } elseif (array_key_exists('only', $options)) {
            $temp = [];

            if (!is_array($options['only'])) {
                $options['only'] = [$options['only']];
            }

            foreach ($options['only'] as $var) {
                if (array_key_exists($var, $vars)) {
                    $temp[$var] = $vars[$var];
                }
            }

            $vars = $temp;
        }

        return empty($vars) ? '' : http_build_query($vars);
    }

    /**
     * Retrieve a URI fragment
     */
    public function getFragment()
    {
        return $this->fragment ?? '';
    }

    /**
     * Returns the segments of the path as an array.
     */
    public function getSegments()
    {
        return $this->segments;
    }

    /**
     * Returns the value of a specific segment of the URI path. Allows to get only existing segments or the next one.
     * @param int $number Segment number starting at 1
     * @param string $default Default value
     * @return string The value of the segment. If you specify the last +1
     *                segment, the $default value. If you specify the last +2
     *                or more throws HTTPException.
     */
    public function getSegment(int $number, string $default = '')
    {
        if ($number < 0 || $number > ($this->getTotalSegments() - 1))
            throw new InvalidArgumentException(
                "The segment index you are looking for is not available. Min number of segments: 0. Max number of segments: " . count($this->segments)
            );

        return $this->segments[$number] ?? $default;
    }

    /**
     * Set the value of a specific segment of the URI path. Allows to set only existing segments or add new one
     * @param int $number Segment number starting at 1
     * @param int|string $value
     * @return self
     */
    public function setSegment(int $number, $value)
    {
        if ($number < 0 || $number > ($this->getTotalSegments() - 1))
            throw new InvalidArgumentException(
                "The segment index you are looking for is not available. Min number of segments: 0. Max number of segments: " . count($this->segments)
            );

        $this->segments[$number] = $value;
        $this->refreshPath();

        return $this;
    }

    /**
     * Returns the total number of segments
     */
    public function getTotalSegments()
    {
        return count($this->segments);
    }

    /**
     * Parses the given string and saves the appropriate authority pieces
     * @return self
     */
    public function setAuthority(string $str)
    {
        $parts = parse_url($str);

        if (!isset($parts['path'])) {
            $parts['path'] = $this->getPath();
        }

        if (empty($parts['host']) && $parts['path'] !== '') {
            $parts['host'] = $parts['path'];
            unset($parts['path']);
        }

        $this->applyParts($parts);
        return $this;
    }

    /**
     * Sets the scheme for this URI
     * @return self
     */
    public function setScheme(string $scheme)
    {
        $uri = clone $this;
        $scheme = strtolower($scheme);
        $uri->scheme = preg_replace('#:(//)?$#', '', $scheme);
        return $uri;
    }

    /**
     * Sets the userInfo/Authority portion of the URI
     * @param string $user The user's username
     * @param string $pass The user's password
     * @return self
     */
    public function setUserInfo(string $user, string $pass)
    {
        $this->user     = trim($user);
        $this->password = trim($pass);
        return $this;
    }

    /**
     * Sets the host name to use
     * @return self
     */
    public function setHost(string $str)
    {
        $this->host = trim($str);

        return $this;
    }

    /**
     * Sets the port portion of the URI
     * @return self
     */
    public function setPort(?int $port = null)
    {
        if ($port === null)
            return $this;

        if ($port <= 0 || $port > 65535)
            throw new InvalidArgumentException("Invalid port. Min number of port: 1. Max number of port: 65535");

        $this->port = $port;
        return $this;
    }

    /**
     * Sets the path portion of the URI
     * @return self
     */
    public function setPath(string $path)
    {
        $this->path = $this->filterPath($path);

        $tempPath = trim($this->path, '/');

        $this->segments = ($tempPath === '') ? [] : explode('/', $tempPath);

        return $this;
    }

    /**
     * Sets the path portion of the URI based on segments
     * @return self
     */
    private function refreshPath()
    {
        $this->path = $this->filterPath(implode('/', $this->segments));

        $tempPath = trim($this->path, '/');

        $this->segments = ($tempPath === '') ? [] : explode('/', $tempPath);

        return $this;
    }

    /**
     * Sets the query portion of the URI, while attempting to clean the various parts of the query keys and values
     * @return self
     */
    public function setQuery(string $query)
    {
        if (!empty($query) && strpos($query, '?') === 0) {
            $query = substr($query, 1);
        }

        if ($this->rawQueryString) {
            $this->query = $this->parseStr($query);
        } else {
            parse_str($query, $this->query);
        }

        return $this;
    }

    /**
     * A convenience method to pass an array of items in as the Query portion of the URI
     * @return self
     */
    public function setQueryArray(array $query)
    {
        $query = http_build_query($query);
        return $this->setQuery($query);
    }

    /**
     * Adds a single new element to the query vars.
     * @param int|string|null $value
     * @return self
     */
    public function addQuery(string $key, $value = null)
    {
        $this->query[$key] = $value;
        return $this;
    }

    /**
     * Removes one or more query vars from the URI
     * @param string ...$params
     * @return self
     */
    public function stripQuery(...$params)
    {
        foreach ($params as $param) {
            unset($this->query[$param]);
        }
        return $this;
    }

    /**
     * Filters the query variables so that only the keys passed in are kept. The rest are removed from the object
     * @param string ...$params
     * @return self
     */
    public function keepQuery(...$params)
    {
        $temp = [];

        foreach ($this->query as $key => $value) {
            if (!in_array($key, $params, true)) {
                continue;
            }

            $temp[$key] = $value;
        }

        $this->query = $temp;
        return $this;
    }

    /**
     * Sets the fragment portion of the URI
     * @return self
     */
    public function setFragment(string $string)
    {
        $this->fragment = trim($string, '# ');
        return $this;
    }

    /**
     * Encodes any dangerous characters, and removes dot segments.
     * While dot segments have valid uses according to the spec,
     * this URI class does not allow them.
     */
    protected function filterPath(?string $path = null): string
    {
        $orig = $path;

        // Decode/normalize percent-encoded chars so
        // we can always have matching for Routes, etc.
        $path = urldecode($path);

        // Remove dot segments
        $path = self::removeDotSegments($path);

        // Fix up some leading slash edge cases...
        if (strpos($orig, './') === 0) {
            $path = '/' . $path;
        }
        if (strpos($orig, '../') === 0) {
            $path = '/' . $path;
        }

        // Encode characters
        $path = preg_replace_callback(
            '/(?:[^' . static::CHAR_UNRESERVED . ':@&=\+\$,\/;%]+|%(?![A-Fa-f0-9]{2}))/',
            static fn (array $matches) => rawurlencode($matches[0]),
            $path
        );

        return $path;
    }

    /**
     * Saves our parts from a parse_url call.
     * @return void
     */
    protected function applyParts(array $parts)
    {
        if (!empty($parts['host'])) {
            $this->host = $parts['host'];
        }
        if (!empty($parts['user'])) {
            $this->user = $parts['user'];
        }
        if (isset($parts['path']) && $parts['path'] !== '') {
            $this->path = $this->filterPath($parts['path']);
        }
        if (!empty($parts['query'])) {
            $this->setQuery($parts['query']);
        }
        if (!empty($parts['fragment'])) {
            $this->fragment = $parts['fragment'];
        }

        // Scheme
        if (isset($parts['scheme'])) {
            $this->setScheme(rtrim($parts['scheme'], ':/'));
        } else {
            $this->setScheme('http');
        }

        // Port
        if (isset($parts['port']) && $parts['port'] !== null) {
            // Valid port numbers are enforced by earlier parse_url or setPort()
            $this->port = $parts['port'];
        }

        if (isset($parts['pass'])) {
            $this->password = $parts['pass'];
        }

        // Populate our segments array
        if (isset($parts['path']) && $parts['path'] !== '') {
            $tempPath = trim($parts['path'], '/');

            $this->segments = ($tempPath === '') ? [] : explode('/', $tempPath);
        }
    }

    /**
     * Combines one URI string with this one based on the rules set out in
     * @return self
     */
    public function resolveRelativeURI(string $uri)
    {
        $relative = new self();
        $relative->setURI($uri);

        if ($relative->getScheme() === $this->getScheme()) {
            $relative->setScheme('');
        }

        $transformed = clone $relative;

        // 5.2.2 Transform References in a non-strict method (no scheme)
        if (!empty($relative->getAuthority())) {
            $transformed
                ->setAuthority($relative->getAuthority())
                ->setPath($relative->getPath())
                ->setQuery($relative->getQuery());
        } else {
            if ($relative->getPath() === '') {
                $transformed->setPath($this->getPath());

                if ($relative->getQuery() !== '') {
                    $transformed->setQuery($relative->getQuery());
                } else {
                    $transformed->setQuery($this->getQuery());
                }
            } else {
                if (strpos($relative->getPath(), '/') === 0) {
                    $transformed->setPath($relative->getPath());
                } else {
                    $transformed->setPath($this->mergePaths($this, $relative));
                }

                $transformed->setQuery($relative->getQuery());
            }

            $transformed->setAuthority($this->getAuthority());
        }

        $transformed->setScheme($this->getScheme());

        $transformed->setFragment($relative->getFragment());

        return $transformed;
    }

    /**
     * Given 2 paths, will merge them according to rules
     */
    protected function mergePaths(self $base, self $reference)
    {
        if (!empty($base->getAuthority()) && $base->getPath() === '') {
            return '/' . ltrim($reference->getPath(), '/ ');
        }

        $path = explode('/', $base->getPath());

        if ($path[0] === '') {
            unset($path[0]);
        }

        array_pop($path);
        $path[] = $reference->getPath();

        return implode('/', $path);
    }

    /**
     * This is equivalent to the native PHP parse_str() function.
     * This version allows the dot to be used as a key of the query string.
     */
    protected function parseStr(string $query)
    {
        $return = [];
        $query  = explode('&', $query);

        $params = array_map(static fn (string $chunk) => preg_replace_callback(
            '/^(?<key>[^&=]+?)(?:\[[^&=]*\])?=(?<value>[^&=]+)/',
            static fn (array $match) => str_replace($match['key'], bin2hex($match['key']), $match[0]),
            urldecode($chunk)
        ), $query);

        $params = implode('&', $params);
        parse_str($params, $result);

        foreach ($result as $key => $value) {
            $return[hex2bin($key)] = $value;
        }

        return $return;
    }

    /**
     * Formats the URI's object as a string
     * @return string
     */
    public function __toString()
    {
        $path   = $this->getPath();
        $scheme = $this->getScheme();

        return self::createURIString(
            $scheme,
            $this->getAuthority(),
            $path, // Absolute URIs should use a "/" for an empty path
            $this->getQuery(),
            $this->getFragment()
        );
    }
}
