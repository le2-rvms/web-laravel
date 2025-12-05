<?php

namespace Database\Factories;

use App\Console\Commands\Sys\ImportAdminAndRoles;
use App\Enum\Admin\AdmUserType;
use App\Models\Admin\Admin;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory
 */
trait UsesJsonFixture
{
    /**
     * 自动根据模型名计算出数据文件，返回随机一条数据，源数据减少。
     */
    protected function shiftShuffleFromPools(): array
    {
        static $pools = [];

        $className = $this->model;

        $classNameBase = class_basename($className);

        if (!array_key_exists($classNameBase, $pools)) {
            $relativePath = database_path("datas/{$classNameBase}.php");
            $data         = require $relativePath;

            if (!is_array($data)) {
                throw new \RuntimeException("fixture {$relativePath} 解析失败");
            }

            shuffle($data);

            $pools[$classNameBase] = $data;
        }

        if (empty($pools[$classNameBase])) {
            throw new \RuntimeException("fixture {$classNameBase} 已经用完，没有更多可用数据。");
        }

        return array_shift($pools[$classNameBase]);
    }

    /**
     * 自动根据模型名计算出数据文件，返回随机一条数据，源数据不变。
     *
     * @param mixed $suffix
     * @param mixed $size
     */
    protected function randomGroupFromPools(string $suffix = '', int $size = 1): array
    {
        static $pools = [];

        $className = $this->model;

        $classNameBase = class_basename($className);

        if (!array_key_exists($classNameBase, $pools)) {
            $suffix = $suffix ? '-'.$suffix : '';

            $relativePath = database_path("datas/{$classNameBase}{$suffix}.php");

            $datas = require $relativePath;

            if (!is_array($datas)) {
                throw new \RuntimeException("fixture {$relativePath} 解析失败");
            }

            $pools[$classNameBase] = $datas;
        } else {
            $datas = $pools[$classNameBase];
        }

        $rand_keys = array_rand($datas, $size);

        if (1 === $size) {
            return $datas[$rand_keys];
        }

        return array_intersect_key($datas, array_flip($rand_keys));
    }

    protected function randomAdminID($size = 2, $query_func = null): array|int
    {
        static $datas = null;

        if (null === $datas) {
            $query = Admin::query()->where('user_type', '!=', AdmUserType::TEMP);
            if ($query_func) {
                $query_func($query);
            }
            $datas = $query->pluck('id')->toArray();
        }

        $rand_keys = array_rand($datas, $size);

        if (1 === $size) {
            return $datas[$rand_keys];
        }

        return $result = array_intersect_key($datas, array_flip($rand_keys));
    }

    protected function randomVehicleServiceAdminID($size = 2): array|int
    {
        static $datas = null;

        if (null === $datas) {
            $query = Admin::query()->where('user_type', '!=', AdmUserType::TEMP)->whereLike('name', '%'.ImportAdminAndRoles::role_vehicle_service.'%');
            $datas = $query->pluck('id')->toArray();
        }

        $rand_keys = array_rand($datas, $size);

        if (1 === $size) {
            return $datas[$rand_keys];
        }

        return $result = array_intersect_key($datas, array_flip($rand_keys));
    }
}
