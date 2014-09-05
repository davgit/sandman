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

use Kisma\Core\Exceptions\FileSystemException;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Shows the freeze:db info
 */
class Show extends ConfigCommand
{
    //******************************************************************************
    //* Methods
    //******************************************************************************

    /**
     * @param string $name
     * @param array  $config
     */
    public function __construct( $name = 'config:show', array $config = array() )
    {
        $_config = array(
            'description' => 'Shows the database configuration for storing frozen objects.',
            'help'        => <<<EOT
The <info>config:show</info> shows all the current freezer configuration settings.

<info>freezer config:show</info>

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

        $output->writeln( 'Freezer Configuration' );

        /** @type Table $_table */
        $_table = $this->getHelper( 'table' );
        $_table->setHeaders( array('Key', 'Value') );

        foreach ( $this->_config as $_key => $_value )
        {
            if ( is_array( $_value ) )
            {
                foreach ( $_value as $_key2 => $_value2 )
                {
                    $_table->addRow( array($_key . '.' . $_key2, $_value2) );
                }
            }
            else
            {
                $_table->addRow( array($_key, $_value) );
            }
        }

        $_table->render( $output );
    }

    /**
     * Loads the current configuration
     */
    protected function _load()
    {
        $_user = posix_getpwuid( posix_getuid() );
        $_path = ( isset( $_user, $_user['dir'] ) ? $_user['dir'] : getcwd() ) . DIRECTORY_SEPARATOR . static::FREEZER_CONFIG_PATH;

        if ( !is_dir( $_path ) )
        {
            if ( false === mkdir( $_path, 0777, true ) )
            {
                throw new FileSystemException( 'Unable to create freezer drop directory: ' . $_path );
            }
        }

        $this->_configFilePath = $_path . DIRECTORY_SEPARATOR . static::FREEZER_CONFIG_FILE;

        if ( !file_exists( $this->_configFilePath ) )
        {
            $this->_save();

            return;
        }

        if ( false === ( $_config = json_decode( file_get_contents( $this->_configFilePath ), true ) ) || JSON_ERROR_NONE != json_last_error() )
        {
            $this->_configFilePath = null;
            throw new \RuntimeException( 'Corrupt or unreadable configuration file "' . $this->_configFilePath . '".' );
        }

        $this->_config = array_merge( $this->_config, $_config );
    }

    /**
     * Saves the configuration file
     *
     * @throws FileSystemException
     */
    protected function _save()
    {
        $_json = json_encode( $this->_config, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );

        if ( false === file_put_contents( $this->_configFilePath, $_json ) )
        {
            $this->_configFilePath = null;
            throw new FileSystemException( 'Error saving configuration file: ' . $this->_configFilePath );
        }
    }
}