<?php
/**
Define some static types for the other classes
*/

abstract class RecurrenceTypes {
	const ONE_TIME = 0;
	const MONTHLY = 1;
	const ANNUAL = 2;
}

abstract class MessageTypes {
	const ERROR = 0;
	const SUCCESS = 1;
	const LOG = 2;
}

abstract class SearchBy {
	const NAME = 0;
	const EMAIL = 1;
} ?>
