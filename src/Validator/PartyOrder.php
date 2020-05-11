<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class PartyOrder extends Constraint {
	public $doubleValueMessage = 'The value "{{ value }}" is already in the sequence.';
	public $unknownValueMessage = 'The value "{{ value }}" is not allowed here.';
}
