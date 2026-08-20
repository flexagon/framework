<?php
/**
 * UrlParamsModel
 *
 * Flexagon : PHP Development Framework
 *
 * @copyright     Copyright (c) 2010-2026 Younghwan Yong
 * @link          https://flexagon.org
 * @license       MIT License (https://opensource.org/licenses/MIT)
 */
namespace _Flexagon\Models;

class UrlParamsModel {
	public ?string $filePath;
	public ?array $filePathArray;
	public ?string $params;
    public ?string $filePathEnd;

    function __construct($filePath, $filePathArray, $params) {
        $this->filePath = $filePath;
        $this->filePathArray = $filePathArray;
        $this->params = $params;
        $this->filePathEnd = end($this->filePathArray);
    }
}
