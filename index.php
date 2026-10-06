<?php
// Lets `php -S localhost:8000` run from the project root during local development.
header('Location: public/', true, 302);
exit;
