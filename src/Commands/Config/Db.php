<?php
/**
 * This file is part of the DreamFactory Freezer(tm)
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
namespace DreamFactory\Tools\Freezer\Commands\Config;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Configures the freeze:db info
 */
class Db extends ConfigCommand
{
    //******************************************************************************
    //* Methods
    //******************************************************************************

    /**
     * @param string $name
     * @param array  $config
     */
    public function __construct( $name = 'config:db', array $config = array() )
    {
        $_config = array(
            'description' => 'Sets the database configuration for storing frozen objects.',
            'definition'  => array(
                new InputArgument( 'host', InputArgument::OPTIONAL, 'The database host name', 'localhost' ),
                new InputArgument( 'port', InputArgument::OPTIONAL, 'The database port', 3306 ),
                new InputArgument( 'database', InputArgument::OPTIONAL, 'The database name', 'dreamfactory' ),
                new InputArgument( 'username', InputArgument::OPTIONAL, 'The database user name', 'dsp_user' ),
                new InputArgument( 'password', InputArgument::OPTIONAL, 'The database user password', 'dsp_user' ),
            ),
            'help'        => <<<EOT
The <info>config:db</info> sets the database connection information for use
with the <comment>freeze:db</comment> command.
<info>freezer config:db host [port] [database] username password</info>

EOT
        );

        parent::__construct( $name, array_merge( $_config, $config ) );
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int|null|void
     * @throws \Exception
     */
    protected function execute( InputInterface $input, OutputInterface $output )
    {
        $this->_load();

        $this->_config['db'] = array(
            'host'     => $input->getArgument( 'host' ),
            'port'     => $input->getArgument( 'port' ),
            'username' => $input->getArgument( 'username' ),
            'password' => $input->getArgument( 'password' ),
            'database' => $input->getArgument( 'database' ),
        );

        $this->_save();

        $output->writeln( '<info>freezer:</info> Database configuration saved.' );
    }
}