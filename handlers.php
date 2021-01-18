<?php

class handleExceptions extends Exception {

}

class handleErrors extends Error {

}

function errorHandler($e) {

}

function exceptionHandler() {

}

set_exception_handler('exceptionHandler');
set_error_handler('errorHandler');