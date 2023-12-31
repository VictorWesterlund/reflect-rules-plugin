<?php

	namespace ReflectRules;

	use \ReflectRules\Rules;

	require_once "Rules.php";

	// Available superglobal scopes
	enum Scope: string {
		case GET  = "_GET";
		case POST = "_POST";
	}

	enum Error {
		case VALUE_MIN_ERROR;
		case VALUE_MAX_ERROR;
		case INVALID_PROPERTY_TYPE;
		case INVALID_PROPERTY_VALUE;
		case MISSING_REQUIRED_PROPERTY;
	}

	class Ruleset {
		// Array of arrays with failed constraints
		private array $errors = [];

		public function __construct() {}

		// Append an error to the array of errors
		private function add_error(Error $error, Scope $scope, string $property, mixed $expected): void {
			// Create sub array if this is the first error in this scope
			if (!array_key_exists($scope->name, $this->errors)) {
				$this->errors[$scope->name] = [];
			}

			// Create sub array if this is the first error for this property
			if (!array_key_exists($property, $this->errors[$scope->name])) {
				$this->errors[$scope->name][$property] = [];
			}

			// Set expected value value for property in scope
			$this->errors[$scope->name][$property][$error->name] = $expected;
		}

		// Evaluate Rules against a given value
		private function eval_rules(Rules $rules, Scope $scope): void {
			// Get the name of the current property being evaluated
			$name = $rules->get_property_name();

			// Check if property name exists in scope
			if (!$rules->eval_required($scope)) {
				// Don't perform further processing if the property is optional and not provided
				if (!$rules->required) {
					return;
				}

				$this->add_error(Error::MISSING_REQUIRED_PROPERTY, $scope, $name, $name);
				return;
			}

			// Get value from scope for the current property
			$value = $GLOBALS[$scope->value][$name];

			/*
				Eval each rule that has been set.
				The error messages will be returned 
			*/

			// Value is not of the correct type or enum value
			if ($rules->types && !$rules->eval_type($value, $scope)) {
				if (!$rules->enum) {
					// Get type names from enum
					$types = array_map(fn(Type $type): string => $type->name, $rules->types);

					$this->add_error(Error::INVALID_PROPERTY_TYPE, $scope, $name, $types);
				} else {
					$this->add_error(Error::INVALID_PROPERTY_VALUE, $scope, $name, $rules->enum);
				}
			}

			if ($rules->min && !$rules->eval_min($value, $scope)) {
				$this->add_error(Error::VALUE_MIN_ERROR, $scope, $name, $rules->min);
			}

			if ($rules->max && !$rules->eval_max($value, $scope)) {
				$this->add_error(Error::VALUE_MAX_ERROR, $scope, $name, $rules->max);
			}
		}

		// ----

		// Perform request processing on GET properties (search parameters)
		public function GET(array $rules): void {
			foreach ($rules as $rule) {
				$this->eval_rules($rule, Scope::GET);
			}
		}

		// Perform request processing on POST properties (request body)
		public function POST(array $rules): void {
			foreach ($rules as $rule) {
				$this->eval_rules($rule, Scope::POST);
			}
		}

		public function is_valid(): bool {
			return empty($this->errors);
		}

		public function get_errors(): array {
			return $this->errors;
		}
	}