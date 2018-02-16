<?php

/**
 * Class Magoffice_Chili_Model_Pdf
 *
 * @category   Magoffice_Chili_Model_Pdf
 * @package    Magoffice_Chili_Model_Pdf
 * @author     Florent TITECA <florent.titeca@cgi.com>
 * @copyright  CGI 2017
 * @version    1.0
 */
class Magoffice_Chili_Model_Pdf extends Chili_Web2print_Model_Pdf
{
    /**
     * updatePdfs : Create task for queued pdfs and download pdfs
     *
     * @param null $orderId
     *
     * @return null
     */
    public function updatePdfs($orderId = null)
    {
        if (!$this->api->isServiceAvailable()) {
            Mage::helper('web2print')->log("updatePdfs has not run because service is not available");

            return null;
        }
        try {
            //First create task id for queued pdfs
            $queuedPdfs = Mage::getModel('web2print/pdf')->getCollection()->addFieldToFilter('status', 'queued');

            if ($orderId) {
                $queuedPdfs->addFieldToFilter('order_increment_id', $orderId);
            }

            if (count($queuedPdfs)) {
                foreach ($queuedPdfs as $queuedPdf) {
                    //Generate task id for pdf item
                    $this->api->createPdfTask($queuedPdf);
                }
            }

            //Second check status of requested pdfs and download
            $requestedPdfs = Mage::getModel('web2print/pdf')->getCollection()->addFieldToFilter(
                'status',
                array('in' => array('requested', 'running', 'queued-chili'))
            );

            if ($orderId) {
                $requestedPdfs->addFieldToFilter('order_increment_id', $orderId);
            }

            if (count($requestedPdfs)) {
                /** @var Magoffice_Chili_Model_Pdf $requestedPdf */
                foreach ($requestedPdfs as $requestedPdf) {
                    /** @var Mage_Core_Model_Website $website */
                    $website = Mage::getModel('sales/order')->loadByIncrementId($requestedPdf->getOrderIncrementId())
                        ->getStore()->getWebsite();

                    $this->api->setWebsite($website->getId());
                    $taskstatus = $this->api->getTaskStatus($requestedPdf->getTaskId());

                    $requestedPdf->setMessage(null);

                    if ($taskstatus == "") {
                        $requestedPdf->setStatus('task-error');
                        $requestedPdf->setMessage('Call to retrieve task returned no result.');
                        $requestedPdf->setUpdatedAt(date('Y-m-d H:i:s'));
                        $requestedPdf->save();
                        continue;
                    }

                    $taskXml = simplexml_load_string($taskstatus);
                    if ($taskXml['found'] == 'false') {
                        $requestedPdf->setStatus('task-error');
                        $requestedPdf->setMessage('Task not found.');
                        $requestedPdf->setUpdatedAt(date('Y-m-d H:i:s'));
                        $requestedPdf->save();
                        continue;
                    }

                    $pdfXml           = simplexml_load_string((string)$taskXml['result']);
                    $pdfUrl           = $pdfXml['url'];
                    $taskErrorMessage = (string)$taskXml['errorMessage'];

                    $taskXml['started']  = (string)$taskXml['started'];
                    $taskXml['finished'] = (string)$taskXml['finished'];

                    /** @var Magoffice_Exaprint_Model_Mysql4_Cardconfig_Collection $cartConfig */
                    $cartConfig = Mage::getModel('magoffice_exaprint/cardconfig')->getCollection();

                    if ($pdfUrl && $taskErrorMessage == '') {
                        if ($requestedPdf->getExportType() == 'frontend') {
                            $type = 'preview';
                            $cartConfig->addFieldToFilter('pdf_preview_id', $requestedPdf->getData('pdf_id'));
                        } elseif ($requestedPdf->getExportType() == 'backend') {
                            $type = 'final';
                            $cartConfig->addFieldToFilter('pdf_final_id', $requestedPdf->getData('pdf_id'));
                        }

                        /** @var Magoffice_Exaprint_Model_Cardconfig $config */
                        $config = $cartConfig->getFirstItem();

                        if ($config->getData('conf_id')) {
                            $num = $config->getData('conf_id');
                        }

                        $filename
                            = 'order_' . $type . '_' . $requestedPdf->getData('order_increment_id') . '_' . $num . '.'
                            . $this->determinFileType($pdfUrl);

                        $fileDir = Mage::helper('web2print')->getPDFSavePath($requestedPdf->getExportType(), $website);

                        $savePath = Mage::getBaseDir() . DS . $fileDir . $filename;
                        $pdf      = Mage::getBaseUrl() . $fileDir . $filename;

                        $exportPath = Mage::getBaseDir() . DS . $fileDir;

                        if (!is_dir($exportPath)) {
                            if (!mkdir($exportPath)) {
                                Mage::log('Error while creating folder : ' . $exportPath);
                            } else {
                                chmod($exportPath, 0775);
                            }
                        }
                        chmod($exportPath, 0775);

                        $downloadFileResponse = $this->downloadFile($pdfUrl, $savePath);

                        if ($downloadFileResponse == 1) {
                            $requestedPdf->setPath($savePath);
                            $requestedPdf->setPdfUrl($pdf);
                            $requestedPdf->setStatus('completed');
                            $requestedPdf->save();

                            //dispatching event
                            Mage::dispatchEvent('web2print_pdf_save_after', array('pdf' => $requestedPdf));
                        } else {
                            $requestedPdf->setStatus('download-error');
                        }
                    } elseif ($taskErrorMessage == '' && $taskXml['started'] == 'True'
                        && $taskXml['finished'] == 'False'
                    ) {
                        $requestedPdf->setStatus('running');
                    } elseif ($taskErrorMessage == '' && $taskXml['started'] != 'True') {
                        $requestedPdf->setStatus('queued-chili');
                        $requestedPdf->setUpdatedAt(date('Y-m-d H:i:s'));
                    } else {
                        $requestedPdf->setStatus('task-error');
                        $requestedPdf->setMessage($taskErrorMessage);
                        $requestedPdf->setUpdatedAt(date('Y-m-d H:i:s'));
                    }
                    $requestedPdf->save();
                }
            }

        } catch (Exception $e) {
            Mage::log($e->getMessage());
        }
    }
}

