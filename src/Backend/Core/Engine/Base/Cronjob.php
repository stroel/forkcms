<?php

namespace Backend\Core\Engine\Base;

/*
 * This file is part of Fork CMS.
 *
 * For the full copyright and license information, please view the license
 * file that was distributed with this source code.
 */

use Symfony\Component\Filesystem\Filesystem;
use Backend\Core\Engine\Exception as BackendException;
use Backend\Core\Engine\Model as BackendModel;

/**
 * This is the base-object for cronjobs. The module-specific cronjob-files can extend the functionality from this class
 */
class Cronjob extends Object
{
    /**
     * The current id
     *
     * @var int
     */
    protected $id;

    /**
     * Clear/removed the busy file
     */
    protected function clearBusyFile()
    {
        $path = $this->getCacheDirectory() . $this->getId() . '.busy';

        // remove the file
        $filesystem = new Filesystem();
        $filesystem->remove($path);
    }

    /**
     * @return string
     */
    protected function getCacheDirectory()
    {
        return BackendModel::getContainer()->getParameter('kernel.cache_dir') . '/cronjobs/';
    }

    public function execute()
    {
    }

    /**
     * Get the id
     *
     * @return int
     */
    public function getId()
    {
        return mb_strtolower($this->getModule() . '_' . $this->getAction());
    }

    /**
     * Set the action
     *
     * We can't rely on the parent setModule function, because a cronjob requires no login
     *
     * @param string $action The action to load.
     * @param string $module The module to load.
     *
     * @throws BackendException If module is not set or the action does not exist
     */
    public function setAction($action, $module = null)
    {
        // set module
        if ($module !== null) {
            $this->setModule($module);
        }

        // check if module is set
        if ($this->getModule() === null) {
            throw new BackendException('Module has not yet been set.');
        }

        // path to look for actions based on the module
        if ($this->getModule() == 'Core') {
            $path = BACKEND_CORE_PATH . '/Cronjobs';
        } else {
            $path = BACKEND_MODULES_PATH . '/' . $this->getModule() . '/Cronjobs';
        }

        // check if file exists
        if (!is_file($path . '/' . $action . '.php')) {
            header('HTTP/1.1 403 Forbidden');
            throw new BackendException('Action not allowed.');
        }

        // set property
        $this->action = (string) $action;
    }

    /**
     * Set the busy file
     */
    protected function setBusyFile()
    {
        // do not set busy file in debug mode
        if ($this->getContainer()->getParameter('kernel.debug')) {
            return;
        }

        // build path
        $filesystem = new Filesystem();
        $path = $this->getCacheDirectory() . $this->getId() . '.busy';

        // init var
        $isBusy = false;

        // does the busy file already exists.
        if ($filesystem->exists($path)) {
            $isBusy = true;

            // grab counter
            $counter = (int) file_get_contents($path);

            // check the counter
            if ($counter > 9) {
                // build class name
                $class = 'Backend\\Modules\\' . $this->getModule() . '\\Cronjobs\\' . $this->getAction();
                if ($this->getModule() == 'Core') {
                    $class = 'Backend\\Core\\Cronjobs\\' . $this->getAction();
                }

                // notify user
                throw new BackendException('Cronjob (' . $class . ') is still busy after 10 runs, check it out!');
            }
        } else {
            $counter = 0;
        }

        // increment counter
        ++$counter;

        // store content
        $filesystem->dumpFile($path, $counter);

        // if the cronjob is busy we should NOT proceed
        if ($isBusy) {
            exit;
        }
    }

    /**
     * Set the module
     *
     * We can't rely on the parent setModule function, because a cronjob requires no login
     *
     * @param string $module The module to load.
     *
     * @throws BackendException If module is not allowed
     */
    public function setModule($module)
    {
        // does this module exist?
        $modules = BackendModel::getModulesOnFilesystem();
        if (!in_array($module, $modules)) {
            // set correct headers
            header('HTTP/1.1 403 Forbidden');

            // throw exception
            throw new BackendException('Module not allowed.');
        }

        // set property
        $this->module = $module;
    }
}
