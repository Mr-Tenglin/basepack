<?php
namespace tenglin\basepack;

use DOMCdataSection;
use DOMDocument;
use DOMElement;
use DOMNamedNodeMap;
use DOMText;
use Exception;

/**
 *
 * project Url: https://github.com/vyuldashev/xml-to-array
 *
 * use: \tenglin\basepack\Xml2Array::convert(xml);
 *
 */

class Xml2Array {
	protected $document;

	public function __construct(string $xml) {
		$this->document = new DOMDocument();
		try {
			$this->document->loadXML($xml);
		} catch (Exception $e) {
			error_log("tips: " . $e->getMessage());
		}
	}

	public static function convert(string $xml): array{
		$converter = new static(trim($xml));
		return $converter->toArray();
	}

	protected function convertAttributes(DOMNamedNodeMap $nodeMap):  ? array{
		if ($nodeMap->length === 0) {
			return null;
		}

		$result = [];
		foreach ($nodeMap as $item) {
			$result[$item->name] = $item->value;
		}
		return ["_attributes" => $result];
	}

	protected function isHomogenous(array $arr) {
		$firstValue = current($arr);
		foreach ($arr as $val) {
			if ($firstValue !== $val) {
				return false;
			}
		}
		return true;
	}

	protected function convertDomElement(DOMElement $element) {
		$sameNames = false;
		$result = $this->convertAttributes($element->attributes);

		if ($element->childNodes->length > 1) {
			$childNodeNames = [];
			foreach ($element->childNodes as $key => $node) {
				$childNodeNames[] = $node->nodeName;
			}
			$sameNames = $this->isHomogenous($childNodeNames);
		}

		foreach ($element->childNodes as $key => $node) {
			if ($node instanceof DOMCdataSection) {
				$result["_cdata"] = $node->data;
				continue;
			}
			if ($node instanceof DOMText) {
				$result = $node->textContent;
				continue;
			}
			if ($node instanceof DOMElement) {
				if ($sameNames) {
					$result[$node->nodeName][$key] = $this->convertDomElement($node);
				} else {
					$result[$node->nodeName] = $this->convertDomElement($node);
				}
				continue;
			}
		}
		return $result;
	}

	public function toArray() : array{
		$result = [];
		if ($this->document->hasChildNodes()) {
			$children = $this->document->childNodes;
			foreach ($children as $child) {
				$result[$child->nodeName] = $this->convertDomElement($child);
			}
		}
		return $result;
	}
}
