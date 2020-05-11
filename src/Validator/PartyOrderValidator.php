<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class PartyOrderValidator extends ConstraintValidator {
	public function validate($value, Constraint $constraint) {
		/* @var $constraint PartyOrder */

		$found   = [];
		$allowed = ['c', 'b', 't', 'a'];

		foreach ($value as $v) {
			if (isset($found[$v])) {
				$this->context->buildViolation($constraint->doubleValueMessage)
					->setParameter('{{ value }}', $v)
					->addViolation();
			} else {
				$found[$v] = true;

				if (!in_array($v, $allowed, true)) {
					$this->context->buildViolation($constraint->unknownValueMessage)
						->setParameter('{{ value }}', $v)
						->addViolation();
				}
			}
		}
	}
}
