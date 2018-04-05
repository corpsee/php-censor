<?php

namespace PHPCensor;

use PHPCensor\Store\Factory;
use PHPCensor\Model\User;
use PHPCensor\Store\UserStore;

class View
{
    protected $vars      = [];
    protected $isContent = false;

    protected static $extension = 'phtml';

    public function __construct($file, $path = null)
    {
        if ('{@content}' === $file) {
            $this->isContent = true;
        } else {
            if (!self::exists($file, $path)) {
                throw new \RuntimeException('View file does not exist: ' . $file);
            }

            $this->viewFile = self::getViewFile($file, $path);
        }
    }

    protected static function getViewFile($file, $path = null)
    {
        $viewPath = is_null($path) ? (SRC_DIR . 'View/') : $path;
        $fullPath = $viewPath . $file . '.' . static::$extension;

        return $fullPath;
    }

    public static function exists($file, $path = null)
    {
        if (!file_exists(self::getViewFile($file, $path))) {
            return false;
        }

        return true;
    }

    public function __isset($var)
    {
        return isset($this->vars[$var]);
    }

    public function __get($var)
    {
        return $this->vars[$var];
    }

    public function __set($var, $val)
    {
        $this->vars[$var] = $val;
    }

    public function render()
    {
        if ($this->isContent) {
            return $this->vars['content'];
        } else {
            extract($this->vars);

            ob_start();

            require($this->viewFile);

            $html = ob_get_contents();
            ob_end_clean();

            return $html;
        }
    }

    /**
     * @return boolean
     */
    protected function loginIsDisabled()
    {
        $config      = Config::getInstance();
        $disableAuth = (boolean)$config->get('php-censor.security.disable_auth', false);

        return $disableAuth;
    }

    /**
     * @return User|null
     */
    protected function getUser()
    {
        if (empty($_SESSION['php-censor-user-id'])) {
            return null;
        }

        /** @var UserStore $userStore */
        $userStore = Factory::getStore('User');

        return $userStore->getById($_SESSION['php-censor-user-id']);
    }
}
