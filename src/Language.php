<?php
/**
 * Created by PhpStorm.
 * User: Inhere
 * Date: 2018/5/6 0006
 * Time: 16:34
 */

namespace SwoKit\Memory;

use Toolkit\Collection\Collection;
use Toolkit\Collection\SimpleCollection;
use Toolkit\File\Directory;
use Toolkit\ArrUtil\Arr;
use Toolkit\ObjUtil\Obj;
use Toolkit\StrUtil\Str;

/**
 * Class Language
 * @package Sws\Memory
 */
class Language extends Collection
{
    const DEFAULT_FILE_KEY = '__default';

    /**
     * current use language
     * @var string
     */
    private $lang = 'en';

    /**
     * current use language
     * @var string[]
     */
    private $langs = [];

    /**
     * The base path language directory.
     * @var string
     */
    private $basePath = '';

    /**
     * the language file type. more see self::FORMAT_*
     * @var string
     */
    private $format = self::FORMAT_PHP;

    /**
     * loaded language file list.
     * @var array
     */
    private $loadedFiles = [];

    /**
     * whether ignore not exists lang file when addLangFile()
     * @var bool
     */
    private $ignoreError = false;

    /**
     * Language constructor.
     * @param array $config
     * @throws \RangeException
     */
    public function __construct(array $config = [])
    {
        parent::__construct();

        Obj::init($this, $config);
    }

    /*********************************************************************************
     * get translate
     *********************************************************************************/

    /**
     * {@inheritdoc}
     * @see self::translate()
     * @throws \InvalidArgumentException
     */
    public function t($key, array $args = [], $lang = null)
    {
        return $this->translate($key, $args, $lang);
    }

    /**
     * {@inheritdoc}
     * @see self::translate()
     * @throws \InvalidArgumentException
     */
    public function tl($key, array $args = [], $lang = null)
    {
        return $this->translate($key, $args, $lang);
    }

    /**
     * {@inheritdoc}
     * @see self::translate()
     * @throws \InvalidArgumentException
     */
    public function trans($key, array $args = [], $lang = null)
    {
        return $this->translate($key, $args, $lang);
    }

    /**
     * get translate message text by key
     * @param string $key 'site-name' or 'user.login'
     * @param array $args
     * @param null|string $lang
     * @return array|string
     * @throws \InvalidArgumentException
     */
    public function translate(string $key, array $args = [], $lang = null)
    {
        if (!\is_string($key)) {
            throw new \InvalidArgumentException('The translate key must be a string.');
        }

        if (!$key = trim($key, ' ' . $this->separator)) {
            throw new \InvalidArgumentException('Cannot translate the empty key');
        }

        list($lang, $key) = $this->parseKey($key, $lang);

        if (!$langData = $this->getLangData($lang, false)) {
            // if ignore error
            if ($this->ignoreError) {
                return ucfirst(Str::toSnakeCase(str_replace(['-', '_', '.'], ' ', $key), ' '));
            }

            throw new \InvalidArgumentException('No language data of the lang: ' . $lang);
        }

        $value = Arr::getByPath($langData, $key, null, $this->separator);

        // no translate text
        if ($value === '' || $value === null) {
            return ucfirst(Str::toSnakeCase(str_replace(['-', '_', '.'], ' ', $key), ' '));
        }

        // $args is not empty
        if ($args) {
            array_unshift($args, $value);

            return sprintf(...$args);
        }

        return $value;
    }

    /**
     * @param string $lang
     * @param bool $toIterator
     * @return array|\ArrayIterator|null
     */
    public function getLangData(string $lang = null, $toIterator = true)
    {
        $lang = $lang ?: $this->lang;

        if (isset($this->data[$lang])) {
            return $toIterator ? new \ArrayIterator($this->data[$lang]) : $this->data[$lang];
        }

        return null;
    }

    /**
     * @param string $key
     * @param null|string $lang
     * @return bool
     */
    public function hasKeyOfLang(string $key, $lang = null): bool
    {
        list($lang, $key) = $this->parseKey($key, $lang);

        if (!$langData = $this->getLangData($lang, false)) {
            return false;
        }

        return Arr::getByPath($langData, $key, null, $this->separator) !== null;
    }

    /*********************************************************************************
     * register lang data
     *********************************************************************************/

    /**
     * @var bool
     */
    private $scanned = false;

    /**
     * scan basePath And Load data
     * @throws \Toolkit\File\Exception\FileNotFoundException
     */
    public function scanAndLoad()
    {
        if ($this->scanned) {
            return $this;
        }

        $this->scanned = true;
        $files = Directory::getFiles($this->basePath, $this->format, true);
        $sfx = '.' . $this->format;
        $langs = array_flip($this->langs);

        foreach ($files as $file) {
            // $lang: file at basePath e.g {basePath}/en.php
            $prefix = $lang = basename($file, $sfx);

            // file at lang dir. e.g {basePath}/en/user.php
            if ($p = strpos($file, '/')) {
                $lang = substr($file, 0, $p);
            }

            // is registered lang name
            if (isset($langs[$lang])) {
                $filePath = $this->basePath . '/' . $file;
//                $this->loadLangFile($lang, $filePath, $this->format)

                $this->loadedFiles[] = $filePath;
                $this->set("{$lang}.{$prefix}", self::read($filePath, $this->format));
            }
        }

        return $this;
    }

    /**
     * @param string $lang
     * @param string $file
     * @param null|string $format
     * @throws \Toolkit\File\Exception\FileNotFoundException
     * @throws \InvalidArgumentException
     */
    public function loadLangFile(string $lang, $file, $format = null)
    {
        if (!is_file($file)) {
            throw new \InvalidArgumentException("the lang file: $file is not exists");
        }

        $format = $format ?: $this->format;
        $prefix = basename($file, '.' . $format);

        $this->loadedFiles[] = $file;
        $this->set("{$lang}.{$prefix}", self::read($file, $format));
    }

    /**
     * @param string $lang
     * @param array $data
     * @return $this
     */
    public function addLangData(string $lang, array $data): self
    {
        if ($langData = $this->getLangData($lang, false)) {
            $this->bindData($langData, $data);
        } else {
            $langData = $data;
        }

        $this->data[$lang] = $langData;

        if (!$this->isLang($lang)) {
            $this->langs[] = $lang;
        }

        return $this;
    }

    /*********************************************************************************
     * override methods
     *********************************************************************************/

    /**
     * {@inheritdoc}
     */
    public function exists($key): bool
    {
        return $this->hasKeyOfLang($key);
    }

    /**
     * {@inheritdoc}
     * @throws \RuntimeException
     */
    public function add($name, $value): SimpleCollection
    {
        throw new \RuntimeException('The method [add] has been disabled');
    }

    /**
     * {@inheritdoc}
     */
    public function set($key, $value)
    {
        list($lang, $key) = $this->parseKey($key);

        $langData = $this->getLangData($lang, false);

        Arr::setByPath($langData, $key, $value, $this->separator);

        $this->data[$lang] = $langData;

        return $this;
    }

    /**
     * @param string $key
     * @param null $default
     * @return mixed
     * @throws \InvalidArgumentException
     */
    public function get(string $key, $default = null)
    {
        return $this->translate($key);
    }

    /*********************************************************************************
     * helper
     *********************************************************************************/

    /**
     * @param string $key
     * @param string $lang
     * @return array
     */
    private function parseKey(string $key, $lang = null): array
    {
        if ($lang) {
            return [$lang, $key];
        }

        if (\strpos($key, $this->separator)) {
            $info = explode($this->separator, $key, 2);

            if ($this->isLang($info[0])) {
                return $info;
            }
        }

        return [$this->lang, $key];
    }

    /*********************************************************************************
     * getter/setter
     *********************************************************************************/

    /**
     * @param string $lang
     * @return bool
     */
    public function hasLang(string $lang): bool
    {
        return $this->isLang($lang);
    }
    public function isLang(string $lang): bool
    {
        return $lang && \in_array($lang, $this->langs,true);
    }

    /**
     * @return string
     */
    public function getLang(): string
    {
        return $this->lang;
    }

    /**
     * @param string $lang
     */
    public function setLang(string $lang)
    {
        $this->lang = trim($lang);
    }

    /**
     * @return string
     */
    public function getBasePath(): string
    {
        return $this->basePath;
    }

    /**
     * @param string|array $path
     * @throws \InvalidArgumentException
     */
    public function setBasePath(string $path)
    {
        if ($path && \is_dir($path)) {
            $this->basePath = $path;
        } else {
            throw new \InvalidArgumentException("The language files path: $path is not exists.");
        }
    }

    /**
     * @return string
     */
    public function getFormat(): string
    {
        return $this->format;
    }

    /**
     * @param string $format
     */
    public function setFormat(string $format)
    {
        if (\in_array($format, self::$formats, true)) {
            $this->format = $format;
        }
    }

    /**
     * @return array
     */
    public function getLoadedFiles(): array
    {
        return $this->loadedFiles;
    }

    /**
     * @return bool
     */
    public function isIgnoreError(): bool
    {
        return $this->ignoreError;
    }

    /**
     * @param bool $ignoreError
     */
    public function setIgnoreError($ignoreError)
    {
        $this->ignoreError = (bool)$ignoreError;
    }

    /**
     * @return \string[]
     */
    public function getLangs(): array
    {
        return $this->langs;
    }

    /**
     * @param \string[] $langs
     */
    public function setLangs(array $langs)
    {
        $this->langs = $langs;
    }
}
