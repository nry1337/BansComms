<?php

namespace Flute\Modules\BansComms\Driver\Items;

use Flute\Core\Database\Entities\Server;
use Flute\Core\Database\Entities\User;
use Flute\Core\Table\TableColumn;
use Flute\Core\Table\TablePreparation;
use Flute\Modules\BansComms\Contracts\DriverInterface;
use Spiral\Database\Injection\Expression;
use Spiral\Database\Injection\Fragment;
use Spiral\Database\Injection\Parameter;

class IKSDriver implements DriverInterface
{
    protected int $sid = 1;

    public function __construct(array $config = [])
    {
        $this->sid = isset($config['sid']) ? (int) $config['sid'] : 1;
    }

    public function getCommsColumns(): array
    {
        return [
            (new TableColumn('created', __('banscomms.table.created')))
                ->setRender("{{CREATED}}", $this->dateFormatRender()),
            (new TableColumn('source', __('banscomms.table.type')))
                ->setRender("{{ICON_TYPE}}", $this->typeFormatRender()),
            new TableColumn('name', __('banscomms.table.loh')),
            (new TableColumn('reason', __('banscomms.table.reason')))->setType('text'),
            (new TableColumn('admin_name', __('banscomms.table.admin')))->setType('text'),
            (new TableColumn('end', __('banscomms.table.end_date')))->setType('text')
                ->setRender("{{ENDS}}", $this->dateFormatRender()),
            (new TableColumn('time', ''))->setType('text')->setVisible(false),
            (new TableColumn('Unbanned', ''))->setType('text')->setVisible(false),
            (new TableColumn('UnbannedBy', ''))->setType('text')->setVisible(false),
            (new TableColumn('', __('banscomms.table.length')))
                ->setSearchable(false)->setOrderable(false)
                ->setRender('{{KEY}}', $this->timeFormatRender()),
        ];
    }

    public function getBansColumns(): array
    {
        return [
            (new TableColumn('created', __('banscomms.table.created')))
                ->setRender("{{CREATED}}", $this->dateFormatRender()),
            new TableColumn('name', __('banscomms.table.loh')),
            (new TableColumn('reason', __('banscomms.table.reason')))->setType('text'),
            (new TableColumn('admin_name', __('banscomms.table.admin')))->setType('text'),
            (new TableColumn('end', __('banscomms.table.end_date')))->setType('text')
                ->setRender("{{ENDS}}", $this->dateFormatRender()),
            (new TableColumn('time', ''))->setType('text')->setVisible(false),
            (new TableColumn('Unbanned', ''))->setType('text')->setVisible(false),
            (new TableColumn('UnbannedBy', ''))->setType('text')->setVisible(false),
            (new TableColumn('', __('banscomms.table.length')))
                ->setSearchable(false)->setOrderable(false)
                ->setRender('{{KEY}}', $this->timeFormatRenderBans()),
        ];
    }

    private function dateFormatRender(): string
    {
        return '
            function(data, type) {
                if (type === "display") {
                    let date = new Date(data * 1000);
                    return ("0" + (date.getMonth() + 1)).slice(-2) + "-" +
                           ("0" + date.getDate()).slice(-2) + "-" +
                           date.getFullYear() + " " +
                           ("0" + date.getHours()).slice(-2) + ":" +
                           ("0" + date.getMinutes()).slice(-2);
                }
                return data;
            }
        ';
    }

    private function typeFormatRender(): string
    {
        return '
            function(data, type) {
                if (type === "display") {
                    return data == "mutes" ? `<i class="type-icon ph-bold ph-microphone-slash"></i>` : `<i class="type-icon ph-bold ph-chat-circle-dots"></i>`;
                }
                return data;
            }
        ';
    }

    private function timeFormatRender(): string
    {
        return "
            function(data, type, full) {
                let time = full[6];
                let ends = full[5];

                if (time == '0') {
                    return '<div class=\"ban-chip bans-forever\">'+ t(\"banscomms.table.forever\") +'</div>';
                } else if (Date.now() >= ends * 1000 && time != '0') {
                    return '<div class=\"ban-chip bans-end\">' + secondsToReadable(time) + '</div>';
                } else {
                    return secondsToReadable(time);
                }
            }
        ";
    }

    private function timeFormatRenderBans(): string
    {
        return "
            function(data, type, full) {
                let time = full[5];
                let ends = full[4];

                if (time == '0') {
                    return '<div class=\"ban-chip bans-forever\">'+ t(\"banscomms.table.forever\") +'</div>';
                } else if (Date.now() >= ends * 1000 && time != '0') {
                    return '<div class=\"ban-chip bans-end\">' + secondsToReadable(time) + '</div>';
                } else {
                    return '<div class=\"ban-chip\">' + secondsToReadable(time) + '</div>';
                }
            }
        ";
    }

    public function getUserStats(int $sid, User $user): array
    {
        // in the future
        return [];
    }

    public function getComms(
        Server $server,
        string $dbname,
        int $page,
        int $perPage,
        int $draw,
        array $columns = [],
        array $search = [],
        array $order = []
    ): array {
        list($selectMutes, $selectGags) = $this->prepareSelectQuery($server, $dbname, $columns, $search, $order, 'comms');

        // Fetch results separately for mutes and gags
        $resultMutes = $selectMutes->fetchAll();
        $resultGags = $selectGags->fetchAll();

        // Merge and slice the results according to pagination
        $mergedResults = array_merge($resultMutes, $resultGags);
        $paginatedResults = array_slice($mergedResults, ($page - 1) * $perPage, $perPage);

        return [
            'draw' => $draw,
            'recordsTotal' => count($mergedResults),
            'recordsFiltered' => count($mergedResults),
            'data' => TablePreparation::normalize(
                ['created', 'source', 'name', 'reason', 'admin_name', 'end', 'time', 'Unbanned', ''],
                $paginatedResults
            )
        ];
    }

    public function getBans(
        Server $server,
        string $dbname,
        int $page,
        int $perPage,
        int $draw,
        array $columns = [],
        array $search = [],
        array $order = []
    ): array {
        $select = $this->prepareSelectQuery($server, $dbname, $columns, $search, $order);

        $paginator = new \Spiral\Pagination\Paginator($perPage);
        $paginate = $paginator->withPage($page)->paginate($select);

        $result = $select->fetchAll();

        return [
            'draw' => $draw,
            'recordsTotal' => $paginate->count(),
            'recordsFiltered' => $paginate->count(),
            'data' => TablePreparation::normalize(
                ['created', 'name', 'reason', 'admin_name', 'end', 'time', 'Unbanned', 'UnbannedBy', ''],
                $result
            )
        ];
    }

    private function prepareSelectQuery(Server $server, string $dbname, array $columns, array $search, array $order, string $table = 'bans')
    {
        if ($table === 'comms') {
            // Initialize an array to hold select queries
            $selectQueries = [];

            foreach (['mutes', 'gags'] as $tableName) {
                $select = $this->buildSelectQuery($dbname, $tableName, $columns, $search, $order, $this->sid);
                array_push($selectQueries, $select);
            }

            return $selectQueries;
        } else {
            return $this->buildSelectQuery($dbname, "bans", $columns, $search, $order, $this->sid, true);
        }
    }

    private function buildSelectQuery(string $dbname, string $tableName, array $columns, array $search, array $order, string $sid, bool $isBans = false)
    {
        $select = dbal()->database($dbname)->table($tableName)->select()->columns([
            "$tableName.*",
            'admins.name as admin_name',
            new Fragment("'$tableName' as source")
        ]);

        // Applying column-based search
        foreach ($columns as $column) {
            if ($column['searchable'] === 'true' && !empty($column['search']['value'])) {
                $select->where($column['name'], 'like', '%' . $column['search']['value'] . '%');
            }
        }

        // Applying global search
        if (isset($search['value']) && !empty($search['value'])) {
            $select->where(function ($select) use ($search, $tableName) {
                $select->where("$tableName.name", 'like', '%' . $search['value'] . '%')
                    ->orWhere("$tableName.reason", 'like', '%' . $search['value'] . '%');
            });
        }

        // Applying ordering
        foreach ($order as $orderItem) {
            $columnIndex = $orderItem['column'];
            $columnName = $columns[$columnIndex]['name'];
            $direction = strtolower($orderItem['dir']) === 'asc' ? 'ASC' : 'DESC';

            if ($columns[$columnIndex]['orderable'] === 'true') {
                $select->orderBy("$tableName.$columnName", $direction);
            }
        }

        // Join with admins table
        $select->innerJoin('admins')->on(["$tableName.adminsid" => 'admins.sid']);

        // Filter by server ID
        $serverIdColumn = "$tableName.server_id";
        $select->where($serverIdColumn, $sid);

        return $select;
    }

    public function getName(): string
    {
        return "IKS Admin";
    }
}