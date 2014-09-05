<?php
/**
 * This file is part of the DreamFactory Sandman(tm)
 *
 * Copyright 2014 DreamFactory Software, Inc. <support@dreamfactory.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
namespace DreamFactory\Tools\Sandman;

use Symfony\Component\Console\Application;

/**
 * Freezes a directory
 */
class Sandman extends Application
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    /**
     * @type string
     */
    const VERSION = '1.0.0';

    //******************************************************************************
    //* Methods
    //******************************************************************************

    /**
     * Constructor.
     *
     * @param string $name    The name of the application
     * @param string $version The version of the application
     *
     * @api
     */
    public function __construct( $name = 'UNKNOWN', $version = 'UNKNOWN' )
    {
        parent::__construct( 'DreamFactory Sandman', static::VERSION );

        //  Config
        $this->add( new Commands\Config\Db() );
        $this->add( new Commands\Config\Show() );

        //  Freeze
        $this->add( new Db() );
        $this->add( new Path() );

        $this->add( new CleanCommand() );
        $this->add( new DefrostCommand() );
        $this->add( new IcemakerCommand() );
    }

}
