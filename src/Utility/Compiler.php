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

use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;

/**
 * The Compiler class compiles sandman into a phar
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Jordi Boggiano <j.boggiano@seld.be>
 * @author Jerry Ablan <jerryablan@dreamfactory.com>
 */
class Compiler
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    /** @type string */
    const DEFAULT_PHAR_FILE_NAME = 'sandman.phar';

    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var string
     */
    protected $_version;
    /**
     * @var string
     */
    protected $_versionDate;

    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * Compiles sandman into a single phar file
     *
     * @throws \RuntimeException
     *
     * @param  string $pharFile The full path to the file to create
     */
    public function compile( $pharFile = self::DEFAULT_PHAR_FILE_NAME )
    {
        if ( file_exists( $pharFile ) )
        {
            unlink( $pharFile );
        }

        /** @var Process $_process */
        if ( 0 != $this->_runProcess( 'git log --pretty="%H" -n1 HEAD', __DIR__, $_process ) )
        {
            throw new \RuntimeException( 'Error while running "git log". Make sure the git binary is in your path and available. Ensure you are running this from your Sandman clone directory as well.' );
        }

        $this->_version = trim( $_process->getOutput() );

        if ( 0 != $this->_runProcess( 'git log -n1 --pretty=%ci HEAD', __DIR__, $_process ) )
        {
            throw new \RuntimeException( 'Error while running "git log". Make sure the git binary is in your path and available. Ensure you are running this from your Sandman clone directory as well.' );
        }

        $_date = new \DateTime( trim( $_process->getOutput() ) );
        $_date->setTimezone( new \DateTimeZone( 'UTC' ) );
        $this->_versionDate = $_date->format( 'Y-m-d H:i:s' );

        if ( 0 == $this->_runProcess( 'git describe --tags HEAD', __DIR__, $_process ) )
        {
            $this->_version = trim( $_process->getOutput() );
        }

        $_phar = new \Phar( $pharFile, 0, static::DEFAULT_PHAR_FILE_NAME );
        $_phar->setSignatureAlgorithm( \Phar::SHA256 );
        $_phar->startBuffering();

        $_finder = new Finder();
        $_finder->files()->ignoreVCS( true )->name( '*.php' )->notName( 'Compiler.php' )->in( __DIR__ . '/..' );

        foreach ( $_finder as $_file )
        {
            $this->_addFile( $_phar, $_file );
        }

        $this->_addSandmanBin( $_phar );

        // Stubs
        $_phar->setStub( $this->getStub() );

        $_phar->stopBuffering();

        if ( extension_loaded( 'gzip' ) )
        {
            $_phar->compressFiles( \Phar::GZ );
        }

        $this->_addFile( $_phar, new \SplFileInfo( __DIR__ . '/../../LICENSE' ), false );

        unset( $_phar );
    }

    /**
     * @param \Phar        $phar
     * @param \SplFileInfo $file
     * @param bool         $strip
     */
    protected function _addFile( $phar, $file, $strip = true )
    {
        $_path = strtr( str_replace( dirname( dirname( __DIR__ ) ) . DIRECTORY_SEPARATOR, '', $file->getRealPath() ), '\\', '/' );

        $_content = file_get_contents( $file );

        if ( $strip )
        {
            $_content = $this->stripWhitespace( $_content );
        }
        elseif ( 'LICENSE' === basename( $file ) )
        {
            $_content = PHP_EOL . $_content . PHP_EOL;
        }

        if ( $_path === 'src/Sandman/Sandman.php' )
        {
            $_content = str_replace( '@package_version@', $this->_version, $_content );
            $_content = str_replace( '@release_date@', $this->_versionDate, $_content );
        }

        $phar->_addFromString( $_path, $_content );
    }

    /**
     * @param \Phar $phar
     */
    protected function _addSandmanBin( $phar )
    {
        $_content = file_get_contents( __DIR__ . '/../../bin/sandman' );
        $_content = preg_replace( '{^#!/usr/bin/env php\s*}', '', $_content );
        $phar->addFromString( 'bin/sandman', $_content );
    }

    /**
     * Removes whitespace from a PHP source string while preserving line numbers.
     *
     * @param  string $source A PHP string
     *
     * @return string The PHP string with the whitespace removed
     */
    protected function stripWhitespace( $source )
    {
        if ( !function_exists( 'token_get_all' ) )
        {
            return $source;
        }

        $output = '';
        foreach ( token_get_all( $source ) as $token )
        {
            if ( is_string( $token ) )
            {
                $output .= $token;
            }
            elseif ( in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT ) ) )
            {
                $output .= str_repeat( "\n", substr_count( $token[1], "\n" ) );
            }
            elseif ( T_WHITESPACE === $token[0] )
            {
                // reduce wide spaces
                $whitespace = preg_replace( '{[ \t]+}', ' ', $token[1] );
                // normalize newlines to \n
                $whitespace = preg_replace( '{(?:\r\n|\r|\n)}', "\n", $whitespace );
                // trim leading spaces
                $whitespace = preg_replace( '{\n +}', "\n", $whitespace );
                $output .= $whitespace;
            }
            else
            {
                $output .= $token[1];
            }
        }

        return $output;
    }

    /**
     * @param string                             $command
     * @param string                             $where
     * @param \Symfony\Component\Process\Process $process
     *
     * @return Process
     */
    protected function _runProcess( $command, $where = null, &$process = null )
    {
        $process = new Process( $command, $where ? : __DIR__ );

        return $process->run();
    }

    /**
     * @return string
     */
    protected function getStub()
    {
        $stub = <<<'EOF'
#!/usr/bin/env php
<?php
/*
 * This file is part of Sandman.
 *
 * (c) Nils Adermann <naderman@naderman.de>
 *     Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view
 * the license that is located at the bottom of this file.
 */

Phar::mapPhar({static::DEFAULT_PHAR_FILE_NAME});

EOF;

        // add warning once the phar is older than 30 days
        if ( preg_match( '{^[a-f0-9]+$}', $this->_version ) )
        {
            $warningTime = time() + 30 * 86400;
            $stub .= "define('SANDMAN_DEV_WARNING_TIME', $warningTime);\n";
        }

        return $stub . <<<'EOF'
require 'phar://sandman.phar/bin/sandman';

__HALT_COMPILER();
EOF;
    }
}
