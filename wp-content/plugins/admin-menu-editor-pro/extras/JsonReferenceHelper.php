<?php

namespace YahnisElsts\JsonReferenceHelper;

use Exception;
use stdClass;

/**
 * Helper class for working with JSON references.
 *
 * JSON references are based on this old draft:
 * https://datatracker.ietf.org/doc/html/draft-pbryan-zyp-json-ref-03
 *
 * See also JSON Pointers:
 * https://datatracker.ietf.org/doc/html/rfc6901
 */
abstract class JsonReferenceHelper {
	/**
	 * Check if a value appears to be a JSON reference.
	 *
	 * In this context, a JSON reference is an associative array with a single key, '$ref'.
	 *
	 * @param mixed $value
	 * @return bool
	 */
	static function isJsonReference($value) {
		return is_array($value) && (count($value) === 1) && !empty($value['$ref']);
	}

	/**
	 * Recursively resolve a possible JSON reference in a document.
	 *
	 * If the value is not a reference, it is returned as-is.
	 *
	 * @param array $document The document to resolve references in.
	 * @param mixed $value The value to resolve. Can be a reference or a regular value.
	 * @return mixed
	 * @throws JsonReferenceException
	 */
	static function maybeResolveReference($document, $value) {
		if ( !self::isJsonReference($value) ) {
			return $value;
		}

		$pointer = $value['$ref'];
		$notFoundMarker = self::getNotFoundMarker();
		$result = self::dereferencePointer($document, $pointer, $notFoundMarker);

		if ( $result === $notFoundMarker ) {
			throw new JsonReferenceNotFound("Cannot resolve pointer: $pointer");
		}

		return $result;
	}

	/**
	 * Get a value from a document using a JSON pointer.
	 *
	 * @param array $document The document to get the value from.
	 * @param string $pointer The JSON pointer to use.
	 * @param mixed $defaultValue The value to return if the pointer is not found. Defaults to NULL.
	 * @return mixed
	 * @throws JsonReferenceException
	 */
	public static function get($document, $pointer, $defaultValue = null) {
		//Just a wrapper around dereferencePointer. We hide the implementation details,
		//like the usedPointers parameter.
		return self::dereferencePointer($document, $pointer, $defaultValue);
	}

	/**
	 * Recursively dereference a  JSON pointer in a document.
	 *
	 * @param array $document The JSON document.
	 * @param string $pointer The pointer to dereference.
	 * @param mixed $defaultValue
	 * @param array $usedPointers
	 * @return mixed
	 * @throws JsonReferenceException
	 */
	private static function dereferencePointer($document, $pointer, $defaultValue = null, $usedPointers = []) {
		if ( empty($pointer) || ($pointer === '/') || ($pointer === '#') ) {
			return $document;
		}

		if ( array_key_exists($pointer, $usedPointers) ) {
			throw new CircularJsonReference("Circular reference detected: $pointer");
		}
		$usedPointers[$pointer] = true;

		$effectivePointer = $pointer;
		//Drop the leading '#' if present. We only support local references.
		if ( substr($effectivePointer, 0, 2) === '#/' ) {
			$effectivePointer = substr($effectivePointer, 1);
		}

		//The pointer should start with a slash.
		if ( $effectivePointer[0] !== '/' ) {
			throw new InvalidJsonPointer("Invalid or unsupported JSON pointer: $pointer");
		}

		$segments = array_filter(explode('/', $effectivePointer), 'strlen');
		$current = $document;
		$notFoundMarker = self::getNotFoundMarker();

		foreach ($segments as $segment) {
			$segment = strtr($segment, ['~1' => '/', '~0' => '~']);

			if ( is_object($current) && property_exists($current, $segment) ) {
				$current = $current->$segment;
			} elseif ( is_array($current) && isset($current[$segment]) ) {
				$current = $current[$segment];
			} else {
				return $defaultValue;
			}

			//Recursively resolve references.
			if ( self::isJsonReference($current) ) {
				$newCurrent = self::dereferencePointer($document, $current['$ref'], $notFoundMarker, $usedPointers);
				if ( $newCurrent === $notFoundMarker ) {
					return $defaultValue;
				}
				$current = $newCurrent;
			}
		}

		return $current;
	}

	private static function getNotFoundMarker() {
		static $marker;
		if ( $marker === null ) {
			$marker = new stdClass();
		}
		return $marker;
	}

	/**
	 * Build a JSON reference from a path.
	 *
	 * @param string[] $path
	 * @return array
	 */
	static function makeReference(array $path) {
		$pointerParts = [];
		foreach ($path as $part) {
			$part = strtr($part, ['~' => '~0', '/' => '~1']);
			$pointerParts[] = $part;
		}

		$pointer = '#/' . implode('/', $pointerParts);
		return ['$ref' => $pointer];
	}
}

class JsonReferenceException extends Exception {
}

class JsonReferenceNotFound extends JsonReferenceException {
}

class CircularJsonReference extends JsonReferenceException {
}

class InvalidJsonPointer extends JsonReferenceException {
}
