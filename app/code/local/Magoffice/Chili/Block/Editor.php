<?php

/**
 * Class Magoffice_Chili_Block_Editor
 *
 * @category     Magoffice
 * @package      Magoffice_Chili
 * @author       Florent TITECA <florent.titeca@cgi.com>
 * @copyright    CGI 2017
 * @version      v1.0
 */
class Magoffice_Chili_Block_Editor extends Chili_Web2print_Block_Editor
{
    /**
     * Function getRectoFormHtml
     *
     * @return bool|string
     * @throws Mage_Core_Exception
     */
    public function getRectoFormHtml()
    {
        if (!$this->getChiliDocumentId()) {
            return false;
        }

        $chiliDocumentId = $this->getChiliDocumentId();
        $form            = '';
        $variables       = '';

        //checken if we can get the form from the cache
        try {
            if ($this->getCacheEditor($chiliDocumentId) && $this->getMode() == 'product') {
                $variables = new SimpleXMLElement($this->getCacheEditor($chiliDocumentId));

            } else {
                $variables = new SimpleXMLElement(
                    Mage::getModel('web2print/api')
                        ->getDocumentVariableDefinitions($chiliDocumentId)
                );

                $xml = new SimpleXMLElement('<?xml version = "1.0" encoding = "UTF-8"?><variables/>');

                $inc = 0;
                foreach ($variables as $variable) {
                    $attributes = $variable->attributes();

                    if ($this->isRectoVersoAllowed(
                            $attributes['displayFilter'],
                            Magoffice_Chili_Helper_Data::CHILI_RECTO_LABEL
                        )
                        && $attributes['name'] != "themeColor"
                        && $attributes['dataType'] == "image"
                    ) {
                        $variable->addAttribute('index', $inc);
                        $domdict = dom_import_simplexml($xml);
                        $domcat  = dom_import_simplexml($variable);
                        $domcat  = $domdict->ownerDocument->importNode($domcat, true);
                        $domdict->appendChild($domcat);
                    }

                    $inc++;
                }

                $variables = $xml;

                if (is_numeric(Mage::helper('web2print')->getCacheLifetimeEditor())
                    && Mage::app()->getCacheInstance()->canUse('chili_forms')
                ) {
                    $cacheName  = 'editorXML_' . $chiliDocumentId;
                    $cacheModel = Mage::getSingleton('core/cache');
                    $cacheModel->save(
                        (string)Mage::getModel('web2print/api')
                            ->getDocumentVariableDefinitions($chiliDocumentId), $cacheName,
                        array("chili_forms"), Mage::helper('web2print')->getCacheLifetimeEditor()
                    );
                }
            }
        } catch (Exception $e) {
            Mage::throwException(
                $this->__('Unable to fetch HTML from based on CHILI document variables: ') .
                $e->getMessage()
            );
        }

        if ($variables->item->count()) {
            $form = $this->renderVariableTypes($variables);
        }

        return $form;
    }

    /**
     * Function getVersoFormHtml
     *
     * @return bool|string
     * @throws Mage_Core_Exception
     */
    public function getVersoFormHtml()
    {
        if (!$this->getChiliDocumentId()) {
            return false;
        }

        $chiliDocumentId = $this->getChiliDocumentId();
        $form            = '';
        $variables       = '';

        //checken if we can get the form from the cache
        try {
            if ($this->getCacheEditor($chiliDocumentId) && $this->getMode() == 'product') {
                $variables = new SimpleXMLElement($this->getCacheEditor($chiliDocumentId));

            } else {
                $variables = new SimpleXMLElement(
                    Mage::getModel('web2print/api')
                        ->getDocumentVariableDefinitions($chiliDocumentId)
                );

                $xml = new SimpleXMLElement('<?xml version = "1.0" encoding = "UTF-8"?><variables/>');

                $inc = 0;
                foreach ($variables as $variable) {
                    $attributes = $variable->attributes();

                    if ($this->isRectoVersoAllowed(
                            $attributes['displayFilter'],
                            Magoffice_Chili_Helper_Data::CHILI_VERSO_LABEL
                        )
                        && $attributes['name'] != "themeColor"
                        && $attributes['dataType'] == "image"
                    ) {
                        $variable->addAttribute('index', $inc);
                        $domdict = dom_import_simplexml($xml);
                        $domcat  = dom_import_simplexml($variable);
                        $domcat  = $domdict->ownerDocument->importNode($domcat, true);
                        $domdict->appendChild($domcat);
                    }

                    $inc++;
                }

                $variables = $xml;

                if (is_numeric(Mage::helper('web2print')->getCacheLifetimeEditor())
                    && Mage::app()->getCacheInstance()->canUse('chili_forms')
                ) {
                    $cacheName  = 'editorXML_' . $chiliDocumentId;
                    $cacheModel = Mage::getSingleton('core/cache');
                    $cacheModel->save(
                        (string)Mage::getModel('web2print/api')
                            ->getDocumentVariableDefinitions($chiliDocumentId), $cacheName,
                        array("chili_forms"), Mage::helper('web2print')->getCacheLifetimeEditor()
                    );
                }
            }
        } catch (Exception $e) {
            Mage::throwException(
                $this->__('Unable to fetch HTML from based on CHILI document variables: ') .
                $e->getMessage()
            );
        }

        if ($variables->item->count()) {
            $form = $this->renderVariableTypes($variables);
        }

        return $form;
    }

    /**
     * @param SimpleXMLElement $data
     *
     * @return string
     */
    private function renderVariableTypes($data)
    {
        $html = "<form id='editorForm' enctype='multipart/form-data'>";
        $html .= "<ul>";

        foreach ($data->item as $item) {
            $attributes = $item->attributes();

            if (isset($attributes['required']) && $attributes['required'] == "true"
                && Mage::getStoreConfig('web2print/editor_page/automatic_validation') == 1
            ) {
                $required = true;
            } else {
                $required = false;
            }

            switch ($attributes['dataType']) {
                case 'list':
                    $html .= $this->getLayout()->createBlock(
                        'Mage_Core_Block_Template', 'editor_dropdown',
                        array('template' => 'web2print/editor/dropdown.phtml')
                    )->setVarNum($attributes['index'])
                        ->setItem($item)
                        ->setRequired($required)->toHtml();
                    break;

                case "checkbox":
                    $html .= $this->getLayout()->createBlock(
                        'Mage_Core_Block_Template', 'editor_checkbox',
                        array('template' => 'web2print/editor/checkbox.phtml')
                    )->setVarNum($attributes['index'])
                        ->setItem($item)
                        ->setRequired($required)->toHtml();
                    break;

                case "longtext":
                    $html .= $this->getLayout()->createBlock(
                        'Mage_Core_Block_Template', 'editor_textarea',
                        array('template' => 'web2print/editor/textarea.phtml')
                    )->setVarNum($attributes['index'])
                        ->setItem($item)
                        ->setRequired($required)->toHtml();
                    break;

                case "image":
                    $html .= $this->getLayout()->createBlock(
                        'Mage_Core_Block_Template', 'editor_image',
                        array('template' => 'web2print/editor/image.phtml')
                    )->setVarNum($attributes['index'])
                        ->setItem($item)
                        ->setRequired($required)->toHtml();
                    break;

                default:
                    $html .= $this->getLayout()->createBlock(
                        'Mage_Core_Block_Template', 'editor_input',
                        array('template' => 'web2print/editor/input.phtml')
                    )->setVarNum($attributes['index'])
                        ->setItem($item)
                        ->setRequired($required)->toHtml();
                    break;
            }
        }

        $html .= "</ul>";
        $html .= "</form>";
        $html
            .= "<script type=\"text/javascript\">
                        //<![CDATA[
                            var editorForm = new VarienForm('editorForm', true);
                        //]]>
                    </script>";

        return $html;
    }

    /**
     * Function getColorFormHtml
     *
     * @return bool|string
     * @throws Mage_Core_Exception
     */
    public function getColorFormHtml()
    {
        if (!$this->getChiliDocumentId()) {
            return false;
        }

        $chiliDocumentId = $this->getChiliDocumentId();
        $html            = '';

        //checken if we can get the form from the cache
        try {
            $variables = new SimpleXMLElement(
                Mage::getModel('web2print/api')
                    ->getDocumentVariableDefinitions($chiliDocumentId)
            );

            $xml = new SimpleXMLElement('<?xml version = "1.0" encoding = "UTF-8"?><variables/>');

            $inc = 0;
            foreach ($variables as $variable) {
                $attributes = $variable->attributes();

                if ($attributes['name'] == "themeColor") {
                    $variable->addAttribute('index', $inc);
                    $domdict = dom_import_simplexml($xml);
                    $domcat  = dom_import_simplexml($variable);
                    $domcat  = $domdict->ownerDocument->importNode($domcat, true);
                    $domdict->appendChild($domcat);
                }

                $inc++;
            }

            $variables = $xml;

            if ($variables->item->count()) {
                foreach ($variables->item as $item) {
                    $attributes = $item->attributes();

                    if (isset($attributes['required']) && $attributes['required'] == "true"
                        && Mage::getStoreConfig('web2print/editor_page/automatic_validation') == 1
                    ) {
                        $required = true;
                    } else {
                        $required = false;
                    }

                    $html .= $this->getLayout()->createBlock(
                        'Mage_Core_Block_Template', 'editor_dropdown',
                        array('template' => 'web2print/editor/color.phtml')
                    )->setVarNum($attributes['index'])
                        ->setItem($item)
                        ->setRequired($required)->toHtml();
                }
            }
        } catch (Exception $e) {
            Mage::throwException(
                $this->__('Unable to fetch HTML from based on CHILI document variables: ') .
                $e->getMessage()
            );
        }

        return $html;
    }

    /**
     * Function getRectoFramesHtml
     *
     * @return bool|string
     */
    public function getRectoFramesHtml()
    {
        if (!$this->getChiliDocumentId()) {
            return false;
        }

        /** @var Mage_Customer_Model_Session $session */
        $session = Mage::getModel('customer/session');

        $chiliDocumentId = $this->getChiliDocumentId();

        /** @var Chili_Web2print_Model_Api $apiModel */
        $apiModel = Mage::getModel('web2print/api');

        $docXml = simplexml_load_string($apiModel->getResourceItemXML($chiliDocumentId));

        $pages       = $docXml->pages;
        $html        = "";
        $frameIndex  = -1;
        $framesArray = array();

        foreach ($pages->item as $page) {
            $pageAttrs = $page->attributes();

            if ($pageAttrs->name == 1) {
                $numPage = $pageAttrs->name - 1;
                foreach ($page->frames->item as $frame) {
                    $frameIndex++;
                    $frameAttrs = $frame->attributes();

                    $tagInSession = false;

                    $tagName = (string)$frameAttrs->tag[0];

                    if (!empty($tagName)) {
                        $tagValue = $session->getData($tagName);
                    }

                    if (isset($tagValue) && !empty($tagValue)) {
                        $tagInSession = true;
                    }

                    if (!$frameAttrs->tag) {
                        continue;
                    }

                    $positionY = (string)$frameAttrs->y;
                    $positionY = (float)explode(' ', $positionY)[0];

                    $positionX = (string)$frameAttrs->x;
                    $positionX = (float)explode(' ', $positionX)[0];

                    //$positionY += 1000;
                    $position = $positionY * 1000 + $positionX;

                    $paramType = Mage::app()->getRequest()->getParam('type');

                    if ($this->isRectoVersoAllowed($frameAttrs->tag, Magoffice_Chili_Helper_Data::CHILI_RECTO_LABEL)) {
                        if ($frame->textFlow) {
                            if ($tagInSession) {
                                $value = ' value = "' . $tagValue . '"';
                            } else if ($paramType == 'edit' && !$tagInSession) {
                                $value = ' value = "' . $frame->textFlow->TextFlow->p->span . '"';
                            } else {
                                $value = "";
                            }
                            if ($paramType == 'edit') {
                                $framesArray[(string)$position]
                                    = '<input type="text" class="editorFrame" id="' . $frameAttrs->id .
                                    '" data-index="' . $numPage . '_' . $frameIndex . '"' .
                                    ' data-tag="' . $frameAttrs->tag . '"' .
                                    ' placeholder="' . $frame->textFlow->TextFlow->p->span . '"' .
                                    $value .
                                    ' onfocus="this.placeholder = \'\'"
                                  onblur="this.placeholder = \' ' . addslashes($frame->textFlow->TextFlow->p->span) . ' \'"
                                /><br>';
                            } else {
                                $framesArray[(string)$position]
                                    = '<input type="text" class="editorFrame" id="' . $frameAttrs->id .
                                    '" data-index="' . $numPage . '_' . $frameIndex . '"' .
                                    ' data-tag="' . $frameAttrs->tag . '"' .
                                    ' placeholder="' . $frame->textFlow->TextFlow->p->span . '"'.
                                    $value .
                                    ' onfocus="this.placeholder = \'\'"
                                  onblur="this.placeholder = \' ' . addslashes($frame->textFlow->TextFlow->p->span) . ' \'"
                                /><br>';
                            }
                        }
                    }
                }
            }
        }

        ksort($framesArray);

        foreach ($framesArray as $frame) {
            $html .= $frame;
        }

        return $html;
    }

    /**
     * Function getVersoFramesHtml
     *
     * @return bool|string
     */
    public function getVersoFramesHtml()
    {
        if (!$this->getChiliDocumentId()) {
            return false;
        }

        /** @var Mage_Customer_Model_Session $session */
        $session = Mage::getModel('customer/session');

        $chiliDocumentId = $this->getChiliDocumentId();

        /** @var Chili_Web2print_Model_Api $apiModel */
        $apiModel = Mage::getModel('web2print/api');

        $docXml      = simplexml_load_string($apiModel->getResourceItemXML($chiliDocumentId));
        $pages       = $docXml->pages;
        $html        = "";
        $frameIndex  = -1;
        $framesArray = array();

        foreach ($pages->item as $page) {
            $pageAttrs = $page->attributes();

            if ($pageAttrs->name == 2) {
                $numPage = $pageAttrs->name - 1;
                foreach ($page->frames->item as $frame) {
                    $frameIndex++;
                    $frameAttrs = $frame->attributes();

                    $tagInSession = false;

                    $tagName = (string)$frameAttrs->tag[0];

                    if (!empty($tagName)) {
                        $tagValue = $session->getData($tagName);
                    }

                    if (isset($tagValue) && !empty($tagValue)) {
                        $tagInSession = true;
                    }

                    if (!$frameAttrs->tag) {
                        continue;
                    }

                    $positionY = (string)$frameAttrs->y;
                    $positionY = (float)explode(' ', $positionY)[0];
                    $positionY += 1000;

                    $paramType = Mage::app()->getRequest()->getParam('type');

                    if ($this->isRectoVersoAllowed($frameAttrs->tag, Magoffice_Chili_Helper_Data::CHILI_VERSO_LABEL)) {
                        if ($frame->textFlow) {
                            if ($tagInSession) {
                                $value = ' value = "' . $tagValue . '"';
                            } else if ($paramType == 'edit' && !$tagInSession) {
                                $value = ' value = "' . $frame->textFlow->TextFlow->p->span . '"';
                            } else {
                                $value = "";
                            }

                            if ($paramType == 'edit') {
                                $framesArray[(string)$positionY]
                                    = '<input type="text" class="editorFrame" id="' . $frameAttrs->id .
                                    '" data-index="' . $numPage . '_' . $frameIndex . '"' .
                                    ' data-tag="' . $frameAttrs->tag . '"' .
                                    ' placeholder="' . $frame->textFlow->TextFlow->p->span . '"' .
                                    $value .
                                    ' onfocus="this.placeholder = \'\'"
                                  onblur="this.placeholder = \' ' . addslashes($frame->textFlow->TextFlow->p->span) . ' \'"
                                /><br>';
                            } else {
                                $framesArray[(string)$positionY]
                                    = '<input type="text" class="editorFrame" id="' . $frameAttrs->id .
                                    '" data-index="' . $numPage . '_' . $frameIndex . '"' .
                                    ' data-tag="' . $frameAttrs->tag . '"' .
                                    ' placeholder="' . $frame->textFlow->TextFlow->p->span . '"' .
                                    $value .
                                    ' onfocus="this.placeholder = \'\'"
                                  onblur="this.placeholder = \' ' . addslashes($frame->textFlow->TextFlow->p->span) . ' \'"
                                /><br>';
                            }
                        }
                    }
                }
            }
        }

        ksort($framesArray);

        foreach ($framesArray as $frame) {
            $html .= $frame;
        }

        return $html;
    }

    /**
     * Function isRectoVersoAllowed
     *
     * @param        $tag
     * @param string $type
     *
     * @return bool
     */
    public function isRectoVersoAllowed($tag, $type = Magoffice_Chili_Helper_Data::CHILI_RECTO_LABEL)
    {
        $tagArray = explode('_', $tag);

        if ($type == Magoffice_Chili_Helper_Data::CHILI_RECTO_LABEL) {
            if (($tagArray[0] == 'r' || $tagArray[0] == 'rv') && $tagArray[1]) {
                return true;
            }
        } elseif ($type == Magoffice_Chili_Helper_Data::CHILI_VERSO_LABEL) {
            if (($tagArray[0] == 'v' || $tagArray[0] == 'rv') && $tagArray[1]) {
                return true;
            }
        }
        return false;
    }

    /**
     * getCurrentCharStyle
     *
     * @return array|bool
     */
    public function getCurrentCharStyle()
    {
        if (!$this->getChiliDocumentId()) {
            return false;
        }

        $chiliDocumentId = $this->getChiliDocumentId();

        /** @var Chili_Web2print_Model_Api $apiModel */
        $apiModel = Mage::getModel('web2print/api');

        $docXml = simplexml_load_string($apiModel->getResourceItemXML($chiliDocumentId));

        $index = 0;

        $currentCharacStyle = array();

        foreach ($docXml->characterStyles->item as $data) {
            $attributes = $data->attributes();
            $pos        = strpos((string)$attributes->name, 'Default');
            if ($pos !== false) {
                $currentCharacStyle['index']   = $index;
                $currentCharacStyle['colorId'] = (string)$attributes->color;
                break;
            }
            $index++;
        }

        return $currentCharacStyle;
    }

    /**
     * getCurrentColor
     *
     * @param $colorId
     *
     * @return array|bool
     */
    public function getCurrentColor($colorId)
    {
        if (!$this->getChiliDocumentId()) {
            return false;
        }

        $chiliDocumentId = $this->getChiliDocumentId();

        /** @var Chili_Web2print_Model_Api $apiModel */
        $apiModel = Mage::getModel('web2print/api');

        $docXml = simplexml_load_string($apiModel->getResourceItemXML($chiliDocumentId));

        $color = array();

        foreach ($docXml->colors->item as $data) {
            $attributes = $data->attributes();
            $attColorId = (string)(string)$attributes->id;

            if ($colorId == $attColorId) {
                $color['label'] = (string)$attributes->name;
                $color['code']  = (string)$attributes->hexValue;
            }
        }

        return $color;
    }

    /**
     * getFonts
     *
     * @return mixed
     */
    public function getFonts()
    {
        /*        $fonts = Mage::getStoreConfig('web2print/configurator/fonts_list');

                return json_decode($fonts);*/

        if (!$this->getChiliDocumentId()) {
            return false;
        }

        $chiliDocumentId = $this->getChiliDocumentId();

        /** @var Chili_Web2print_Model_Api $apiModel */
        $apiModel = Mage::getModel('web2print/api');

        $docXml = simplexml_load_string($apiModel->getResourceItemXML($chiliDocumentId));

        $fontList = array();

        foreach ($docXml->fonts->item as $data) {
            $attributes = $data->attributes();

            $fontId            = (string)$attributes->id;
            $fontList[$fontId] = (string)$attributes->name;
        }

        return $fontList;
    }

    /**
     * getFontSizes
     *
     * @return array
     */
    public function getFontSizes()
    {
        $min = (int)Mage::getStoreConfig('web2print/configurator/min_font_size');
        $max = (int)Mage::getStoreConfig('web2print/configurator/max_font_size');

        $fontsize = array();

        for ($i = $min; $i <= $max; $i++) {
            $fontsize[] = $i;
        }

        return $fontsize;
    }

    /**
     * getColorPickerList
     *
     * @return mixed
     */
    public function getColorPickerList()
    {
        $colorList = json_decode(Mage::getStoreConfig('web2print/configurator/font_colors_list'),true);

        return $colorList;
    }

    /**
     * getConfigColorList
     *
     * @return array|bool
     */
    public function getConfigColorList()
    {
        if (!$this->getChiliDocumentId()) {
            return false;
        }

        $chiliDocumentId = $this->getChiliDocumentId();

        /** @var Chili_Web2print_Model_Api $apiModel */
        $apiModel = Mage::getModel('web2print/api');

        $docXml = simplexml_load_string($apiModel->getResourceItemXML($chiliDocumentId));

        $colorList = array();

        foreach ($docXml->colors->item as $data) {
            $attributes = $data->attributes();

            // if color label begins by 'txt_', show it on the configurator
            $customColor = stripos($attributes->name, 'txt');
            if ($customColor !== false) {
                $colorId                      = (string)$attributes->id;
                $colorList[$colorId]['label'] = (string)$attributes->name;
                $colorList[$colorId]['code']  = (string)$attributes->hexValue;
            }
        }

        return $colorList;
    }
}
