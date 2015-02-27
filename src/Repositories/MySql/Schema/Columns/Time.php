<?php

/*
 *	Copyright 2015 RhubarbPHP
 *
 *  Licensed under the Apache License, Version 2.0 (the "License");
 *  you may not use this file except in compliance with the License.
 *  You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 *  Unless required by applicable law or agreed to in writing, software
 *  distributed under the License is distributed on an "AS IS" BASIS,
 *  WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 *  See the License for the specific language governing permissions and
 *  limitations under the License.
 */

namespace Rhubarb\Stem\Repositories\MySql\Schema\Columns;

use Rhubarb\Crown\DateTime\RhubarbTime;

class Time extends \Rhubarb\Stem\Schema\Columns\Time
{
    use MySqlColumn;

    public function __construct($columnName)
    {
        parent::__construct($columnName, "00:00:00");
    }

    public function getDefinition()
    {
        $sql = "`" . $this->columnName . "` time " . $this->getDefaultDefinition();
        return $sql;
    }

    public function getTransformIntoRepository()
    {
        return function ($data) {
            $data = new RhubarbTime($data);

            if ($data->isValidDateTime()) {
                $date = $data->format("H:i:s");
            } else {
                $date = "00:00:00";
            }

            return $date;
        };
    }

    public function getTransformFromRepository()
    {
        return function ($data) {
            $date = new RhubarbTime($data);

            return $date;
        };
    }
} 