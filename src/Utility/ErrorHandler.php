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
namespace DreamFactory\Sandman\Utility;

/**
 * Convert PHP errors into exceptions
 *
 * @author Artem Lopata <biozshock@gmail.com>
 * @author Jerry Ablan <jerryablan@dreamfactory.com>
 */
class ErrorHandler
{
    /**
     * Error handler
     *
     * @param int    $level   Level of the error raised
     * @param string $message Error message
     * @param string $file    Filename that the error was raised in
     * @param int    $line    Line number the error was raised at
     *
     * @static
     * @throws \ErrorException
     */
    public static function handle( $level, $message, $file, $line )
    {
        // respect error_reporting being disabled
        if ( !error_reporting() )
        {
            return;
        }

        if ( ini_get( 'xdebug.scream' ) )
        {
            $message .=
                PHP_EOL .
                PHP_EOL .
                "Warning: You have xdebug.scream enabled, the warning above may be" .
                PHP_EOL .
                "a legitimately suppressed error that you were not supposed to see.";
        }

        throw new \ErrorException( $message, 0, $level, $file, $line );
    }

    /**
     * Register error handler
     *
     * @static
     */
    public static function register()
    {
        set_error_handler( array( __CLASS__, 'handle' ) );
    }
}
