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

namespace Rhubarb\Stem\Repositories;

use Rhubarb\Stem\Aggregates\Aggregate;
use Rhubarb\Stem\Collections\Collection;
use Rhubarb\Stem\Exceptions\RecordNotFoundException;
use Rhubarb\Stem\Exceptions\SortNotValidException;
use Rhubarb\Stem\Models\Model;
use Rhubarb\Stem\Schema\Columns\Float;
use Rhubarb\Stem\Schema\Columns\Integer;

/**
 * The base class for data repositories.
 *
 * A repository acts as an intermediary between data objects and the data providers. It thus
 * allows for caching and other data manipulations to occur either as part of the
 * default implementation or as a dependency injection.
 *
 * In addition to this base class, two implementations are of primary use to us:
 *
 * Offline - used where no backend storage is needed and for unit testing
 * MySql - used where mysql is required as the back end storage.
 *
 * @see \Rhubarb\Stem\Repositories\Offline\Offline
 * @see \Rhubarb\Stem\Repositories\MySql\MySql
 */
abstract class Repository
{
    /**
     * The collection of cached object data.
     *
     * @var array
     */
    public $cachedObjectData = array();

    /**
     * Stores the class name for the default repository used by dataobjects.
     *
     * Change this by calling setDefaultRepositoryClassName()
     *
     * @see Repository::setDefaultRepositoryClassName();
     * @var string
     */
    private static $defaultRepositoryClassName = "\Rhubarb\Stem\Repositories\Offline\Offline";

    /**
     * A collection of closures allowing data to processed in and out of the repository at a column level.
     *
     * Closures are used here for performance to avoid call methods for every column of every row if not required.
     *
     * @var array
     */
    protected $columnTransforms = [];

    /**
     * @var \Rhubarb\Stem\Schema\ModelSchema;
     */
    protected $schema;

    protected $modelClassName;

    public function __construct(Model $model)
    {
        $this->modelClassName = get_class($model);
        $this->schema = $model->generateSchema();

        $columns = $this->schema->getColumns();

        foreach ($columns as $column) {
            $this->columnTransforms[$column->columnName] =
                [
                    $column->getTransformFromRepository(),
                    $column->getTransformIntoRepository()
                ];
        }
    }

    public function getModelClass()
    {
        return $this->modelClassName;
    }

    /**
     * Gets the schema object for this repository.
     *
     * @return \Rhubarb\Stem\Schema\ModelSchema
     */
    public function getSchema()
    {
        return $this->schema;
    }

    /**
     * Get's an array of unique identifiers for the given DataFilter
     *
     * Used normally to hydrate data lists with their data.
     *
     * @param Collection $list
     * @param int $unfetchedRowCount An output parameter containing the number of rows left unfetched (if ranging)
     * @param array $relationshipNavigationPropertiesToAutoHydrate An array of property names the caller suggests we
     *                                                             try to auto hydrate (if supported)
     * @return array
     */
    public function getUniqueIdentifiersForDataList(
        Collection $list,
        &$unfetchedRowCount = 0,
        $relationshipNavigationPropertiesToAutoHydrate = []
    ) {
        // For now just returning all items in the collection.
        return array_keys($this->cachedObjectData);
    }

    /**
     * Computes the given aggregates and returns an array of answers
     *
     * An answer will be null if the repository is unable to answer it.
     *
     * @param Aggregate[] $aggregates
     * @param Collection $collection
     * @return array
     */
    public function calculateAggregates($aggregates, Collection $collection)
    {
        return [];
    }

    public function canFilterExclusivelyByRepository(Collection $collection)
    {
        return false;
    }

    /**
     * Returns the sorts needed for manual sorting.
     *
     * @param Collection $list
     * @return array
     */
    protected function getManualSortsRequiredForList(Collection $list)
    {
        return $list->getSorts();
    }

    /**
     * Get's a sorted list of unique identifiers for the supplied list.
     *
     * @param Collection $list
     * @throws \Rhubarb\Stem\Exceptions\SortNotValidException
     * @return array
     */
    public function getSortedUniqueIdentifiersForDataList(Collection $list)
    {
        $sorts = $this->getManualSortsRequiredForList($list);

        if (sizeof($sorts) == 0) {
            return false;
        }

        $schema = $list->getModelSchema();
        $columns = $schema->getColumns();

        $arrays = [];
        $directions = [];
        $types = [];

        $ids = [];

        $x = 0;

        $list->disableRanging();

        foreach ($list as $item) {
            $ids[$x] = $item->getUniqueIdentifier();
            $x++;
        }

        foreach ($sorts as $columnName => $ascending) {
            $arrays[$columnName] = [];

            $type = SORT_STRING;

            $column = null;

            if (isset($columns[$columnName])) {
                $column = $columns[$columnName];

                if ($column instanceof Integer || $column instanceof Float) {
                    $type = SORT_NUMERIC;
                }
            } else {
                $type = SORT_REGULAR;
            }

            $types[$columnName] = $type;
            $directions[$columnName] = ($ascending) ? SORT_ASC : SORT_DESC;

            $x = 0;

            foreach ($list as $item) {
                if (!isset($item[$columnName])) {
                    // If the 'column' contains a dot, we are accessing a relationship or a magical
                    // method. It is then not appropriate to say that we can't sort just because the first
                    // (or any) row doesn't have the value. Instead set the value to an empty string.

                    if (strpos($columnName, ".") !== false) {
                        $itemValue = "";
                    } else {
                        throw new SortNotValidException($columnName);
                    }
                } else {
                    $itemValue = $item[$columnName];
                }

                $arrays[$columnName][$x] = $itemValue;
                $x++;
            }
        }

        $list->enableRanging();

        if (sizeof($arrays)) {
            $params = array();

            foreach ($arrays as $column => $data) {
                $params[] = &$arrays[$column];
                $params[] = $directions[$column];
                $params[] = $types[$column];
            }

            $params[] = &$ids;

            call_user_func_array("array_multisort", $params);
        }

        return array_values($ids);
    }

    /**
     * Clear's the repository of all it's cached data.
     */
    public function clearObjectCache()
    {
        $this->cachedObjectData = array();
    }

    /**
     * Returns a new default repository of the current default repository type.
     *
     * @see Repository::setDefaultRepositoryClassName()
     * @param \Rhubarb\Stem\Models\Model $forModel
     * @internal param \Rhubarb\Stem\Schema\ModelSchema $forSchema
     * @return mixed
     */
    public static function getNewDefaultRepository(Model $forModel)
    {
        $defaultRepository = self::$defaultRepositoryClassName;

        return new $defaultRepository($forModel);
    }

    /**
     * Takes the model data from the data object and stores it in the repository cache.
     *
     * @param \Rhubarb\Stem\Models\Model $object
     */
    protected final function cacheObjectData(Model $object)
    {
        $uniqueIdentifier = $object->UniqueIdentifier;

        if ($uniqueIdentifier === null) {
            return;
        }

        if (!isset($this->cachedObjectData[$uniqueIdentifier])) {
            $this->cachedObjectData[$uniqueIdentifier] = $object->exportRawData();
        } else {
            $this->cachedObjectData[$uniqueIdentifier] = array_merge($this->cachedObjectData[$uniqueIdentifier],
                $object->exportRawData());
        }
    }

    /**
     * Removes the model data of the data object from the repository cache.
     *
     * @param \Rhubarb\Stem\Models\Model $object
     */
    protected final function deleteObjectFromCache(Model $object)
    {
        $uniqueIdentifier = $object->UniqueIdentifier;

        if ($uniqueIdentifier === null) {
            return;
        }

        if (isset($this->cachedObjectData[$uniqueIdentifier])) {
            unset($this->cachedObjectData[$uniqueIdentifier]);
        }
    }

    /**
     * Returns the model data for a given object.
     *
     * Uses cached data if it exists and if not will request that the object is hydrated.
     *
     * @see Repository::fetchMissingObjectData()
     * @param \Rhubarb\Stem\Models\Model $object
     * @param $uniqueIdentifier
     * @param array $relationshipsToAutoHydrate An array of relationship names which should be automatically hydrated
     *                                            (i.e. joined) during the hydration of this object. Not supported by all
     *                                            Repositories.
     * @return mixed
     */
    protected final function fetchObjectData(Model $object, $uniqueIdentifier, $relationshipsToAutoHydrate = [])
    {
        if (!isset($this->cachedObjectData[$uniqueIdentifier])) {
            $this->cachedObjectData[$uniqueIdentifier] = $this->fetchMissingObjectData($object, $uniqueIdentifier,
                $relationshipsToAutoHydrate);
        }

        return $this->cachedObjectData[$uniqueIdentifier];
    }

    /**
     * Fetches new data for a given object.
     *
     * This function should be overriden by Repository implementations to fetch the data
     * from it's back end data store.
     *
     * @param \Rhubarb\Stem\Models\Model $object
     * @param $uniqueIdentifier
     * @param array $relationshipsToAutoHydrate An array of relationship names which should be automatically hydrated
     *                                            (i.e. joined) during the hydration of this object. Not supported by all
     *                                            Repositories.
     * @throws RecordNotFoundException
     */
    protected function fetchMissingObjectData(Model $object, $uniqueIdentifier, $relationshipsToAutoHydrate = [])
    {
        throw new RecordNotFoundException(get_class($object), $uniqueIdentifier);
    }

    /**
     * Fetches the data for an object and populates it's model data with the same.
     *
     * @param \Rhubarb\Stem\Models\Model $object
     * @param $uniqueIdentifier
     * @param array $relationshipsToAutoHydrate An array of relationship names which should be automatically hydrated
     *                                            (i.e. joined) during the hydration of this object. Not supported by all
     *                                            Repositories.
     */
    public final function hydrateObject(Model $object, $uniqueIdentifier, $relationshipsToAutoHydrate = [])
    {
        $objectData = $this->fetchObjectData($object, $uniqueIdentifier, $relationshipsToAutoHydrate);

        $object->importRawData($objectData);
    }

    /**
     * Rehydrates the model fresh from the back end data store.
     *
     * @param Model $object
     * @param $uniqueIdentifier
     */
    public function reHydrateObject(Model $object, $uniqueIdentifier)
    {
        $this->hydrateObject($object, $uniqueIdentifier);
    }

    /**
     * save the data object.
     *
     * In the base implementation the object is only cached, however this function also
     * calls onObjectSaved() to allow extenders to store the object permanently in a
     * back end data store.
     *
     * @see Repository::onObjectSaved()
     * @throws \Rhubarb\Stem\Exceptions\ModelException When the object has no unique identifier.
     * @param \Rhubarb\Stem\Models\Model $object
     */
    public final function saveObject(Model $object)
    {
        $this->onObjectSaved($object);

        if ($object->getUniqueIdentifier() === null) {
            throw new \Rhubarb\Stem\Exceptions\ModelException("The object could not be saved as it has no unique identifier.",
                $object);
        }

        $this->cacheObjectData($object);
    }

    public final function deleteObject(Model $object)
    {
        if ($object->isNewRecord()) {
            return;
        }

        $this->onObjectDeleted($object);

        $this->deleteObjectFromCache($object);
    }

    /**
     * Called just before an object is deleted in deleteObject()
     *
     * Normally used to perform the actual deletion in the back end
     *
     * @param Repository ::deleteObject()
     * @param \Rhubarb\Stem\Models\Model $object
     */
    protected function onObjectDeleted(Model $object)
    {

    }

    /**
     * Called just before an object is saved in saveObject()
     *
     * Normally used to perform the actual storage in the back end and update the object
     * with an auto number identifier if necessary.
     *
     * @param Repository ::saveObject()
     * @param \Rhubarb\Stem\Models\Model $object
     */
    protected function onObjectSaved(Model $object)
    {

    }

    /**
     * Changes the default class name for new repositories.
     *
     * @see Repository::getNewDefaultRepository();
     * @throws \Rhubarb\Stem\Exceptions\ModelException When the class name doesn't exist.
     * @param $repositoryClassName
     */
    public static function setDefaultRepositoryClassName($repositoryClassName)
    {
        if (!class_exists($repositoryClassName)) {
            throw new \Rhubarb\Stem\Exceptions\ModelException("Sorry the class name '$repositoryClassName' does not exist and so cannot be used as a default repository class name.",
                null);
        }

        self::$defaultRepositoryClassName = $repositoryClassName;
    }

    /**
     * Get's the default repository class name being used.
     *
     * @return string
     */
    public static function getDefaultRepositoryClassName()
    {
        return self::$defaultRepositoryClassName;
    }
}