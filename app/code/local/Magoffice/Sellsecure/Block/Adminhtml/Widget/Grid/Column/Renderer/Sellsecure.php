<?php

/**
 * Class Magoffice_Sellsecure_Block_Adminhtml_Widget_Grid_Column_Renderer_Sellsecure
 *
 * @category     Magoffice
 * @package      Magoffice_Sellsecure
 * @author       Florent TITECA <florent.titeca@cgi.com>
 * @copyright    CGI 2016
 * @version      v1.0
 */
class Magoffice_Sellsecure_Block_Adminhtml_Widget_Grid_Column_Renderer_Sellsecure
    extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    /**
     * @param Varien_Object $row
     * @return string
     */
    public function render(Varien_Object $row)
    {
        $html = '';

        if ($row->sellsecure_state != null) {
            $html = $this->_renderSellsecure($row);
        }

        return ($html);
    }

    /**
     * @param Varien_Object $row
     * @return string
     */
    protected function _renderSellsecure(Varien_Object $row)
    {
        $state = $row->sellsecure_state;
        $evaluation = $row->sellsecure_eval;

        switch ($state) {
            case('SCORING_FORCED'):
            case('SCORING_WAITING_EVAL'):
                $html = '<span title="'. $this->__("En cours d'évaluation") . '"
                    style="border:2px solid #666666;border-radius:5px;font-weight:bold;color:#737373;background:#F3F3F3;
                    width:35px;text-align:center;display:inline-block;">
                    ...
                    </span>';
                break;
            case('SCORING_ERROR'):
                $html = '<span title="'. $this->__("Erreur") . '"
                    style="border:2px solid #CE0A0A;border-radius:5px;font-weight:bold;color:#CE0A0A;background:white;
                    width:35px;text-align:center;display:inline-block;">
                    X
                    </span>';
                break;
            case('SCORING_DONE'):
                $maxScore = Mage::getStoreConfig('magoffice_sellsecure/threshold_evaluation/max_score');
                $reliableScore = Mage::getStoreConfig('magoffice_sellsecure/threshold_evaluation/reliable_score');
                $warningScore = Mage::getStoreConfig('magoffice_sellsecure/threshold_evaluation/warning_score');

                if ($evaluation >= $maxScore) {
                    $html = '<span title="'. $this->__("Ok") . '"
                    style="border:2px solid #295015;border-radius:5px;font-weight:bold;color:#FFF;background:#6AA84F;
                    width:35px;text-align:center;display:inline-block;">
                    ' . $evaluation . '
                    </span>';
                } else if ($evaluation >= $reliableScore) {
                    $html = '<span title="'. $this->__("Ok") . '"
                    style="border:2px solid #38761D;border-radius:5px;font-weight:bold;color:#FFF;background:#B6D7A8;
                    width:35px;text-align:center;display:inline-block;">
                    ' . $evaluation . '
                    </span>';
                } else if ($evaluation >= $warningScore) {
                    $html = '<span title="'. $this->__("Attention") . '"
                    style="border:2px solid #7F6000;border-radius:5px;font-weight:bold;color:#FFF;background:#F1C232;
                    width:35px;text-align:center;display:inline-block;">
                    ' . $evaluation . '
                    </span>';
                } else {
                    $html = '<span title="'. $this->__("Pas fiable") . '"
                    style="border:2px solid #660000;border-radius:5px;font-weight:bold;color:#FFF;background:#CC0000;
                    width:35px;text-align:center;display:inline-block;">
                    ' . $evaluation . '
                    </span>';
                }
                break;
            default:
                $html = '';
                break;
        }

        $siteIdCmc = Mage::getStoreConfig('magoffice_sellsecure/login/sideidcmc');
        $siteIdFac = Mage::getStoreConfig('magoffice_sellsecure/login/siteidfac');

        $sellsecureUrl = Mage::getStoreConfig('magoffice_sellsecure/flow_url_mapping/url_service_evaluation_detail');
        $sellsecureUrl = str_replace('{RefID}', $row->IncrementId, $sellsecureUrl);
        $sellsecureUrl = str_replace('{siteidcmc}', $siteIdCmc, $sellsecureUrl);
        $sellsecureUrl = str_replace('{siteidfac}', $siteIdFac, $sellsecureUrl);

        if ($sellsecureUrl) {
            $html = '<a href="' . $sellsecureUrl . '" target="_blank" style="text-decoration: none;">' . $html . '</a>';
        }

        return ($html);
    }
}
