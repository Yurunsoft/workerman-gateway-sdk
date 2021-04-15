<?php

require dirname(__DIR__) . '/vendor/autoload.php';

// Server
echo 'Starting server...', \PHP_EOL;
echo shell_exec('php ' . __DIR__ . '/server/start.php stop'), \PHP_EOL;
echo shell_exec('php ' . __DIR__ . '/server/start.php start -d'), \PHP_EOL;

register_shutdown_function(function () {
    // stop server
    echo 'Stoping http server...', \PHP_EOL;
    echo shell_exec('php ' . __DIR__ . '/server/start.php stop'), \PHP_EOL;
    echo 'Http Server stoped!', \PHP_EOL;
});

$serverStarted = false;
for ($i = 0; $i < 10; ++$i)
{
    try
    {
        $sock = socket_create(\AF_INET, \SOCK_STREAM, \SOL_TCP);
        if ($sock && socket_set_option($sock, \SOL_SOCKET, \SO_RCVTIMEO, ['sec' => 1, 'usec' => 0]) && @socket_connect($sock, '127.0.0.1', 2900))
        {
            $serverStarted = true;
            break;
        }
    }
    finally
    {
        socket_close($sock);
    }
    sleep(1);
}
if ($serverStarted)
{
    echo 'Server started!', \PHP_EOL;
}
else
{
    throw new \RuntimeException('Server start failed');
}
