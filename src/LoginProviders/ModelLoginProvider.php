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

namespace Rhubarb\Stem\LoginProviders;

use Rhubarb\Crown\Encryption\HashProvider;
use Rhubarb\Crown\Exceptions\ImplementationException;
use Rhubarb\Crown\LoginProviders\Exceptions\LoginDisabledException;
use Rhubarb\Crown\LoginProviders\Exceptions\LoginFailedException;
use Rhubarb\Crown\LoginProviders\Exceptions\NotLoggedInException;
use Rhubarb\Crown\LoginProviders\LoginProvider;
use Rhubarb\Stem\Collections\Collection;
use Rhubarb\Stem\Filters\Equals;
use Rhubarb\Stem\Models\Model;
use Rhubarb\Stem\Schema\SolutionSchema;

/**
 */
class ModelLoginProvider extends LoginProvider
{
    private $usernameColumnName = "";
    private $passwordColumnName = "";
    private $activeColumnName = "";
    private $modelClassName = "";

    public function __construct($modelClassName, $usernameColumnName, $passwordColumnName, $activeColumnName = "")
    {
        parent::__construct();

        $this->modelClassName = $modelClassName;
        $this->usernameColumnName = $usernameColumnName;
        $this->passwordColumnName = $passwordColumnName;
        $this->activeColumnName = $activeColumnName;
    }

    protected function isModelActive($model)
    {
        return ($model[$this->activeColumnName] == true);
    }

    public function login($username, $password)
    {
        // We don't allow spaces around our usernames and passwords
        $username = trim($username);
        $password = trim($password);

        if ($username == "") {
            throw new LoginFailedException();
        }

        $list = new Collection($this->modelClassName);
        $list->filter(new Equals($this->usernameColumnName, $username));

        if (!sizeof($list)) {
            throw new LoginFailedException();
        }

        $hashProvider = HashProvider::getHashProvider();

        // There should only be one user matching the username. It would be possible to support
        // unique *combinations* of username and password but it's a potential security issue and
        // could trip us up when supporting the project.
        if (sizeof($list) > 1) {
            throw new LoginFailedException();
        }

        $user = $list[0];

        $this->checkUserIsPermitted($user);

        // Test the password matches.
        $userPasswordHash = $user[$this->passwordColumnName];

        if ($hashProvider->compareHash($password, $userPasswordHash)) {
            // Matching login - but is it enabled?
            if ($this->isModelActive($user)) {
                $this->LoggedIn = true;
                $this->LoggedInUserIdentifier = $user->getUniqueIdentifier();

                $this->storeSession();

                return true;
            } else {
                throw new LoginDisabledException();
            }
        }

        throw new LoginFailedException();
    }

    /**
     * Provides a way to set the logged in user based on having the user's model.
     *
     * This is used by things like Api Authentication and makes sure that the means by which permissions are
     * determined is exactly the same as for any other part of the solution.
     *
     * @param Model $user Required
     * @throws ImplementationException
     */
    public function forceLogin(Model $user = null)
    {
        // The model parameter must be optional to comply with PHP Strict Mode method override rules and as the model
        // is actually required, this ensures that it is provided
        if ($user === null) {
            throw new ImplementationException('A model is required to force login');
        }

        $this->LoggedInUserIdentifier = $user->UniqueIdentifier;

        parent::forceLogin();
    }

    /**
     * Provides an opportunity for extending classes to do additional checks on the user object before
     * allowing them to login.
     *
     * You should throw an exception if you want to prevent the login.
     *
     * @param $user
     */
    protected function checkUserIsPermitted($user)
    {

    }

    /**
     * Returns the model object for the logged in user.
     *
     * @return \Rhubarb\Stem\Models\Model
     * @throws NotLoggedInException
     */
    public function getModel()
    {
        if (!$this->isLoggedIn()) {
            throw new NotLoggedInException();
        }

        if (isset($this->LoggedInUserIdentifier)) {
            try {
                return SolutionSchema::getModel($this->modelClassName, $this->LoggedInUserIdentifier);
            } catch (\Rhubarb\Stem\Exceptions\RecordNotFoundException $er) {
                throw new NotLoggedInException();
            }
        }

        throw new NotLoggedInException();
    }

    protected function onLogOut()
    {
        // Remove the logged in user identifier from the session.
        unset($this->LoggedInUserIdentifier);

        parent::onLogOut();
    }
}
