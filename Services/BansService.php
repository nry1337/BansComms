<?php

namespace Flute\Modules\BansComms\Services;
use Flute\Core\Database\Entities\User;

class BansService
{
    protected BansCommsService $service;

    public function __construct( BansCommsService $bansCommsService )
    {
        $this->service = $bansCommsService;
    }

    public function generateTable( ?int $sid = null )
    {
        return $this->service->generateTable($sid, 'bans');
    }

    public function getServerModes()
    {
        return $this->service->getServerModes();
    }

    /**
     * Fetches the data for a specific server based on various parameters.
     *
     * @param User $user 
     * @param int $page Page number.
     * @param int $perPage Number of items per page.
     * @param int $draw Draw counter.
     * @param array $columns Column configuration.
     * @param array $search Search configuration.
     * @param array $order Order configuration.
     * @param int|null $sid Server ID.
     * @return array Data from the driver.
     * @throws \Exception If the module is not configured or server is not found.
     */
    public function getUserData(
        User $user,
        int $page,
        int $perPage,
        int $draw,
        array $columns = [],
        array $search = [],
        array $order = [],
        ?int $sid = null
    ) {
        $this->service->validateServerModes();

        $server = $this->service->getServerFromModes($sid);

        $factory = $this->service->getDriverFactory($server);

        return $factory->getUserBans(
            $user,
            $server['server'],
            $server['db'],
            $page,
            $perPage,
            $draw,
            $columns,
            $search,
            $order
        );
    }

    /**
     * Fetches the data for a specific server based on various parameters.
     *
     * @param int $page Page number.
     * @param int $perPage Number of items per page.
     * @param int $draw Draw counter.
     * @param array $columns Column configuration.
     * @param array $search Search configuration.
     * @param array $order Order configuration.
     * @param int|null $sid Server ID.
     * @return array Data from the driver.
     * @throws \Exception If the module is not configured or server is not found.
     */
    public function getData(
        int $page,
        int $perPage,
        int $draw,
        array $columns = [],
        array $search = [],
        array $order = [],
        ?int $sid = null
    ) {
        $this->service->validateServerModes();

        $server = $this->service->getServerFromModes($sid);

        $factory = $this->service->getDriverFactory($server);

        return $factory->getBans(
            $server['server'],
            $server['db'],
            $page,
            $perPage,
            $draw,
            $columns,
            $search,
            $order
        );
    }
}