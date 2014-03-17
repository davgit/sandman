<?php
/**
 * This file is part of DreamFactory Sandman(tm)
 *
 * Copyright (c) 2014 DreamFactory Software, Inc. <support@dreamfactory.com>
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
namespace DreamFactory\Sandman;

use DreamFactory\Sandman\Utility\ErrorHandler;
use Kisma\Core\Utility\FileSystem;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Helper\DialogHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Sandman
 *
 * @author    Jerry Ablan <jerryablan@dreamfactory.com>
 */
class Sandman extends Application
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    /**
     * @type string
     */
    const PACKAGE_VERSION = '{package_version}';
    /**
     * @type string
     */
    const RELEASE_DATE = '{release_date}';
    /**
     * @type string
     */
    const PHAR_MARKER = 'phar:';

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * @param string $name
     * @param string $version
     */
    public function __construct( $name = 'Sandman', $version = self::PACKAGE_VERSION )
    {
        if ( function_exists( 'ini_set' ) )
        {
            ini_set( 'xdebug.show_exception_trace', false );
            ini_set( 'xdebug.scream', false );
        }

        if ( function_exists( 'date_default_timezone_set' ) && function_exists( 'date_default_timezone_get' ) )
        {
            date_default_timezone_set( @date_default_timezone_get() );
        }

        ErrorHandler::register();

        parent::__construct( $name, $version );
    }

    /**
     * {@inheritDoc}
     */
    public function run( InputInterface $input = null, OutputInterface $output = null )
    {
        if ( null === $output )
        {
            $_styles = array(
                'highlight' => new OutputFormatterStyle( 'red' ),
                'warning'   => new OutputFormatterStyle( 'black', 'yellow' ),
            );

            $_formatter = new OutputFormatter( null, $_styles );
            $output = new ConsoleOutput( ConsoleOutput::VERBOSITY_NORMAL, null, $_formatter );
        }

        return parent::run( $input, $output );
    }

    /**
     * {@inheritDoc}
     */
    public function doRun( InputInterface $input, OutputInterface $output )
    {
        if ( version_compare( PHP_VERSION, '5.3.2', '<' ) )
        {
            $output->writeln(
                '<warning>Composer only officially supports PHP 5.4.0 and above, you will most likely encounter problems with your PHP ' .
                PHP_VERSION .
                '. Upgrading is strongly recommended.</warning>'
            );
        }

        if ( defined( 'SANDMAN_DEV_WARNING_TIME' ) && $this->getCommandName( $input ) !== 'self-update' && $this->getCommandName( $input ) !== 'selfupdate' )
        {
            if ( time() > SANDMAN_DEV_WARNING_TIME )
            {
                $output->writeln(
                    sprintf(
                        '<warning>Warning: This development build of sandman is over 30 days old. It is recommended to update it by running "%s self-update" to get the latest version.</warning>',
                        $_SERVER['PHP_SELF']
                    )
                );
            }
        }

        if ( getenv( 'SANDMAN_NO_INTERACTION' ) )
        {
            $input->setInteractive( false );
        }

        if ( $input->hasParameterOption( '--profile' ) )
        {
            $_startTime = microtime( true );
        }

        $_workingDirectory = $this->_getWorkingDirectory( $input );

        if ( !empty( $_workingDirectory ) )
        {
            $_priorDirectory = getcwd();
            chdir( $_workingDirectory );
        }

        $_result = parent::doRun( $input, $output );

        if ( isset( $_priorDirectory ) )
        {
            chdir( $_priorDirectory );
        }

        if ( isset( $_startTime ) )
        {
            $output->writeln(
                '<info>Memory usage: ' .
                round( memory_get_usage() / 1024 / 1024, 2 ) .
                'MB (peak: ' .
                round( memory_get_peak_usage() / 1024 / 1024, 2 ) .
                'MB), time: ' .
                round( microtime( true ) - $_startTime, 2 ) .
                's'
            );
        }

        return $_result;
    }

    /**
     * @param  InputInterface $input
     *
     * @return mixed
     * @throws \RuntimeException
     */
    protected function _getWorkingDirectory( InputInterface $input )
    {
        $_path = $input->getParameterOption( array( '--working-dir', '-d' ) );

        if ( false !== $_path && !is_dir( $_path ) )
        {
            throw new \RuntimeException( 'Invalid working directory specified.' );
        }

        return $_path;
    }

    /**
     * Initializes all the composer commands
     */
    protected function getDefaultCommands()
    {
        $_classes = FileSystem::glob( __DIR__ . '/Commands/*.php' );

        $_commands = parent::getDefaultCommands();

        if ( !empty( $_classes ) )
        {
            foreach ( $_classes as $_class )
            {
                $_class = __NAMESPACE__ . '\\Commands\\' . str_ireplace( '.php', null, $_class );
                $_commands[] = new $_class();
            }
        }

        if ( static::PHAR_MARKER === substr( __FILE__, 0, 5 ) )
        {
            $_commands[] = new Commands\SelfUpdateCommand();
        }

        return $_commands;
    }

    /**
     * {@inheritDoc}
     */
    public function getLongVersion()
    {
        return parent::getLongVersion() . ' ' . static::RELEASE_DATE;
    }

    /**
     * {@inheritDoc}
     */
    protected function getDefaultInputDefinition()
    {
        $_definition = parent::getDefaultInputDefinition();

        $_definition->addOption(
            new InputOption( '--profile', null, InputOption::VALUE_NONE, 'Display timing and memory usage information' )
        );

        $_definition->addOption(
            new InputOption( '--working-dir', '-d', InputOption::VALUE_REQUIRED, 'If specified, use the given directory as working directory.' )
        );

        return $_definition;
    }

    /**
     * {@inheritDoc}
     */
    protected function getDefaultHelperSet()
    {
        $_helperSet = parent::getDefaultHelperSet();

        $_helperSet->set( new DialogHelper() );

        return $_helperSet;
    }
}
