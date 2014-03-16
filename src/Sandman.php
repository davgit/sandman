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

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputInterface;

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
    const PACKAGE_VERSION = '0.1.0';
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
            $_styles = Factory::createAdditionalStyles();
            $_formatter = new OutputFormatter( null, $_styles );
            $_output = new ConsoleOutput( ConsoleOutput::VERBOSITY_NORMAL, null, $_formatter );
        }

        return parent::run( $input, $output );
    }

    /**
     * {@inheritDoc}
     */
    public function doRun( InputInterface $input, OutputInterface $output )
    {
        $this->io = new ConsoleIO( $input, $output, $this->getHelperSet() );

        if ( version_compare( PHP_VERSION, '5.3.2', '<' ) )
        {
            $output->writeln(
                '<warning>Composer only officially supports PHP 5.3.2 and above, you will most likely encounter problems with your PHP ' .
                PHP_VERSION .
                ', upgrading is strongly recommended.</warning>'
            );
        }

        if ( defined( 'COMPOSER_DEV_WARNING_TIME' ) && $this->getCommandName( $input ) !== 'self-update' && $this->getCommandName( $input ) !== 'selfupdate' )
        {
            if ( time() > COMPOSER_DEV_WARNING_TIME )
            {
                $output->writeln(
                    sprintf(
                        '<warning>Warning: This development build of composer is over 30 days old. It is recommended to update it by running "%s self-update" to get the latest version.</warning>',
                        $_SERVER['PHP_SELF']
                    )
                );
            }
        }

        if ( getenv( 'COMPOSER_NO_INTERACTION' ) )
        {
            $input->setInteractive( false );
        }

        if ( $input->hasParameterOption( '--profile' ) )
        {
            $startTime = microtime( true );
            $this->io->enableDebugging( $startTime );
        }

        if ( $newWorkDir = $this->getNewWorkingDir( $input ) )
        {
            $oldWorkingDir = getcwd();
            chdir( $newWorkDir );
        }

        $result = parent::doRun( $input, $output );

        if ( isset( $oldWorkingDir ) )
        {
            chdir( $oldWorkingDir );
        }

        if ( isset( $startTime ) )
        {
            $output->writeln(
                '<info>Memory usage: ' .
                round( memory_get_usage() / 1024 / 1024, 2 ) .
                'MB (peak: ' .
                round( memory_get_peak_usage() / 1024 / 1024, 2 ) .
                'MB), time: ' .
                round( microtime( true ) - $startTime, 2 ) .
                's'
            );
        }

        return $result;
    }

    /**
     * @param  InputInterface $input
     *
     * @throws \RuntimeException
     */
    private function getNewWorkingDir( InputInterface $input )
    {
        $workingDir = $input->getParameterOption( array( '--working-dir', '-d' ) );
        if ( false !== $workingDir && !is_dir( $workingDir ) )
        {
            throw new \RuntimeException( 'Invalid working directory specified.' );
        }

        return $workingDir;
    }

    /**
     * {@inheritDoc}
     */
    public function renderException( $exception, $output )
    {
        try
        {
            $composer = $this->getComposer( false );
            if ( $composer )
            {
                $config = $composer->getConfig();

                $minSpaceFree = 1024 * 1024;
                if ( ( ( $df = @disk_free_space( $dir = $config->get( 'home' ) ) ) !== false && $df < $minSpaceFree ) ||
                     ( ( $df = @disk_free_space( $dir = $config->get( 'vendor-dir' ) ) ) !== false && $df < $minSpaceFree )
                )
                {
                    $output->writeln( '<error>The disk hosting ' . $dir . ' is full, this may be the cause of the following exception</error>' );
                }
            }
        }
        catch ( \Exception $e )
        {
        }

        return parent::renderException( $exception, $output );
    }

    /**
     * @param  bool $required
     * @param  bool $disablePlugins
     *
     * @throws JsonValidationException
     * @return \Composer\Composer
     */
    public function getComposer( $required = true, $disablePlugins = false )
    {
        if ( null === $this->composer )
        {
            try
            {
                $this->composer = Factory::create( $this->io, null, $disablePlugins );
            }
            catch ( \InvalidArgumentException $e )
            {
                if ( $required )
                {
                    $this->io->write( $e->getMessage() );
                    exit( 1 );
                }
            }
            catch ( JsonValidationException $e )
            {
                $errors = ' - ' . implode( PHP_EOL . ' - ', $e->getErrors() );
                $message = $e->getMessage() . ':' . PHP_EOL . $errors;
                throw new JsonValidationException( $message );
            }

        }

        return $this->composer;
    }

    /**
     * @return IOInterface
     */
    public function getIO()
    {
        return $this->io;
    }

    public function getHelp()
    {
        return self::$logo . parent::getHelp();
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
                $_class = str_ireplace( '.php', null, $_class );
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
        return parent::getLongVersion() . ' ' . Composer::RELEASE_DATE;
    }

    /**
     * {@inheritDoc}
     */
    protected function getDefaultInputDefinition()
    {
        $definition = parent::getDefaultInputDefinition();
        $definition->addOption( new InputOption( '--profile', null, InputOption::VALUE_NONE, 'Display timing and memory usage information' ) );
        $definition->addOption(
            new InputOption( '--working-dir', '-d', InputOption::VALUE_REQUIRED, 'If specified, use the given directory as working directory.' )
        );

        return $definition;
    }

    /**
     * {@inheritDoc}
     */
    protected function getDefaultHelperSet()
    {
        $helperSet = parent::getDefaultHelperSet();

        $helperSet->set( new DialogHelper() );

        return $helperSet;
    }
}
