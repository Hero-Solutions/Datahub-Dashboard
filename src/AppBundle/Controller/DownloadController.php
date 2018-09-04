<?php
namespace AppBundle\Controller;

use AppBundle\Util\RecordUtil;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;

class DownloadController extends Controller
{
    private $provider = null;
    private $dataDef = null;
    private $question = null;
    private $field = null;

    /**
     * @Route("/download/{provider}/{aspect}/{parameter}/{question}/{graph}/{field}", name="download", requirements={"provider"="[^/]+", "aspect"="[^/]+", "parameter"="[^/]+", "question"="[^/]+", "graph"="[^/]+", "field"="[^/]+"})
     */
    public function download($provider, $aspect = '', $parameter = '', $question = '', $graph = '', $field = '')
    {
        $this->provider = $provider;
        $this->question = $question;
        if($field !== '')
            $this->field = $field;

        $this->dataDef = $this->getParameter('data_definition');
        $leftMenu = $this->getParameter('left_menu');

        $functionCall = null;
        $parameters = $leftMenu[ucfirst($aspect)];
        foreach($parameters as $param) {
            if($param['url'] === $parameter) {
                foreach ($param['list'] as $quest) {
                    if($quest['url'] === $question) {
                        $functionCall = $quest['function'];
                        break;
                    }
                }
            }
        }
        if(!$functionCall) {
            throw new NotFoundHttpException('Deze downloadpagina bestaat niet.');
        }

        $functionCall .= ucfirst($graph);
        $csvData = $this->$functionCall();

        // Generate response
        $response = new Response();
        if($this->field) {
            if(array_key_exists($field, $this->dataDef) && array_key_exists('csv', $this->dataDef[$field])) {
                $label = $this->dataDef[$field]['csv'];
            }
            else {
                $label = $question . '_' . $field;
            }
            $filename = $provider . '_' . $aspect . '_' . $label . '.csv';
        } else {
            $filename = $provider . '_' . $aspect . '_' . $question . '.csv';
        }

        // Set headers
        $response->headers->set('Cache-Control', 'private');
        $response->headers->set('Content-type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '";');
        $response->headers->set('Content-length', strlen($csvData));
        $response->sendHeaders();

        $response->setContent($csvData);

        return $response;
    }

    private function getDocumentManager()
    {
        return $this->get('doctrine_mongodb')->getManager();
    }

    private function getAllRecords()
    {
        return $this->getDocumentManager()->getRepository('RecordBundle:Record')->findBy(array('provider' => $this->provider));
    }

    private function extractFieldFromRecord($record, $field)
    {
        if(strpos($field, '/')) {
            $parts = explode('/', $field);
            if($record[$parts[0]] && count($record[$parts[0]]) > 0) {
                foreach($record[$parts[0]] as $part) {
                    if($part[$parts[1]] && count($part[$parts[1]])) {
                        return $part[$parts[1]];
                    }
                }
            }
        }
        elseif($record[$field] && count($record[$field])) {
            return $record[$field];
        }
        return null;
    }

    private function getRecordIds($record)
    {
        $applicationId = '';
        if ($record['application_id'] && count($record['application_id']) > 0) {
            $applicationId = $record['application_id'][0];
        }
        $objectNumber = '';
        if ($record['object_number'] && count($record['object_number']) > 0) {
            $objectNumber = $record['object_number'][0];
        }
        return array($applicationId, $objectNumber);
    }

    private function fieldOverview()
    {
        $records = $this->getAllRecords();

        $csvData = '';
        if($records) {
            foreach ($records as $record) {
                $data = $record->getData();
                $recordIds = $this->getRecordIds($data);
                $part = $this->extractFieldFromRecord($data, $this->field);
                $csvData .= PHP_EOL . $recordIds[0] . ',' . $recordIds[1] . ',' . ($part ? 'ingevuld' : 'niet ingevuld');
            }
        }

        $label = RecordUtil::getFieldLabel($this->field, $this->dataDef);

        return 'Applicatie ID,Objectnummer,' . $label . $csvData;
    }

    private function minFieldOverviewBarchart()
    {
        return $this->fieldOverview();
    }

    private function basicFieldOverviewBarchart()
    {
        return $this->fieldOverview();
    }

    private function extendedFieldOverviewBarchart()
    {
        return $this->fieldOverview();
    }

    private function ambigIdsCmp($a, $b)
    {
        if($a['count'] == 0) {
            return 1;
        } elseif($b['count'] == 0) {
            return -1;
        } elseif ($a['count'] == $b['count']) {
            return 0;
        } else {
            return ($a['count'] < $b['count']) ? 1 : -1;
        }
    }

    private function ambigIds($field, $label)
    {
        $records = $this->getAllRecords();
        $ids = array();
        $csvArray = array();
        if($records) {
            foreach ($records as $record) {
                $data = $record->getData();
                if ($data[$field] && count($data[$field]) > 0) {
                    $id = $data[$field][0];
                    if (!array_key_exists($id, $ids)) {
                        $ids[$id] = 1;
                    } else {
                        $ids[$id]++;
                    }
                }
            }

            foreach ($records as $record) {
                $data = $record->getData();
                if($data[$field] && count($data[$field]) > 0) {
                    $id = $data[$field][0];
                    $count = $ids[$id];
                }
                else {
                    $id = '';
                    $count = 0;
                }
                $recordIds = $this->getRecordIds($data);
                $csvArray[] = array('app_id' => $recordIds[0], 'obj_number' => $recordIds[1], 'id' => $id, 'count' => $count);
            }
            usort($csvArray, array('AppBundle\Controller\DownloadController', 'ambigIdsCmp'));
        }

        $csvData = '';
        foreach($csvArray as $csvLine) {
            $csvData .= PHP_EOL . '"' . $csvLine['app_id'] . '","' . $csvLine['obj_number'] . '","' . $csvLine['id'] . '","' . $csvLine['count'] . '"';
        }

        return 'Applicatie ID,Objectnummer,' . $label . ',Aantal voorkomens' . $csvData;
    }

    private function ambigWorkPidsPiechart()
    {
        return $this->ambigIds('work_pid', 'Work PID');
    }

    private function ambigDataPidsPiechart()
    {
        return $this->ambigIds('data_pid', 'Data PID');
    }

    private function ambigtermPie($field)
    {
        $records = $this->getAllRecords();

        $termsWithId = array();
        $termsWithoutId = array();
        $localAuthority = null;
        if($records) {
            foreach ($records as $record) {
                $data = $record->getData();
                $fieldValues = $this->extractFieldFromRecord($data, $field);
                if ($fieldValues) {
                    foreach ($fieldValues as $fieldValue) {
                        if ($fieldValue['term'] && count($fieldValue['term']) > 0) {
                            if ($fieldValue['id'] && count($fieldValue['id']) > 0) {
                                $ids = $fieldValue['id'];
                                $localId = '';
                                $localAuthority_ = '';
                                foreach($ids as $id) {
                                    if($id['type'] === 'local') {
                                        $localId = $id['id'];
                                        $localAuthority_ = $id['source'];
                                        if($localAuthority) {
                                            if($localAuthority_ !== $localAuthority) {
                                                $localAuthority = '';
                                            }
                                        } else {
                                            $localAuthority = $localAuthority_;
                                        }
                                        break;
                                    }
                                }
                                $isEmpty = true;
                                foreach ($ids as $id) {
                                    if ($id['type'] === 'local') {
                                        continue;
                                    }
                                    $isEmpty = false;
                                    $authority = $this->getAuthority($id);
                                    if (!array_key_exists($fieldValue['term'][0], $termsWithId)) {
                                        $termsWithId[$fieldValue['term'][0]] = array(array('local_id' => $localId, 'concept_id' => $id['id'], 'authority' => $authority));
                                    } else {
                                        $isIn = false;
                                        foreach ($termsWithId[$fieldValue['term'][0]] as $knownId) {
                                            if ($knownId['concept_id'] === $id['id']) {
                                                $isIn = true;
                                                break;
                                            }
                                        }
                                        if (!$isIn) {
                                            $termsWithId[$fieldValue['term'][0]][] = array('local_id' => $localId, 'concept_id' => $id['id'], 'authority' => $authority);
                                        }
                                    }
                                }
                                if($isEmpty) {
                                    if (!array_key_exists($fieldValue['term'][0], $termsWithId)) {
                                        $termsWithId[$fieldValue['term'][0]] = array(array('local_id' => $localId, 'concept_id' => '', 'authority' => ''));
                                    }
                                }
                            } else {
                                if (!array_key_exists($fieldValue['term'][0], $termsWithoutId)) {
                                    $termsWithoutId[$fieldValue['term'][0]] = '';
                                }
                            }
                        }
                    }
                }
            }
        }

        $csvData = '';
        foreach($termsWithoutId as $term => $id) {
            $csvData .= PHP_EOL . '"","' . $term . '",""';
        }
        foreach($termsWithId as $term => $ids) {
            foreach($ids as $id) {
                $csvData .= PHP_EOL . '"' . $id['local_id'] . '","' . $term . '","' . $id['concept_id'] . '","' . $id['authority'] . '"';
            }
        }

        $label = RecordUtil::getFieldLabel($field, $this->dataDef);

        if(!$localAuthority) {
            $localAuthority = ' ';
        } else {
            $localAuthority .= ' ';
        }

        return $localAuthority . 'ID,' . $label . ',Concept ID,Authority' . $csvData;
    }

    private function getAuthority($id)
    {
        if($id['type'] === 'local') {
            $authority = $id['source'];
        } else {
            $parsedUrl = parse_url($id['id']);
            if(array_key_exists('scheme', $parsedUrl) && strlen($parsedUrl['scheme']) > 0) {
                $authority = $parsedUrl['scheme'] . '://';
            }
            if(array_key_exists('host', $parsedUrl) && strlen($parsedUrl['host']) > 0) {
                $authority .= $parsedUrl['host'];
            } else {
                $authority = $id['source'];
            }
        }
        return $authority;
    }

    private function ambigtermBar($field)
    {
        $records = $this->getAllRecords();

        $termsWithId = array();
        $termsWithoutId = array();
        if($records) {
            foreach ($records as $record) {
                $data = $record->getData();
                $fieldValues = $this->extractFieldFromRecord($data, $field);
                if ($fieldValues) {
                    foreach ($fieldValues as $fieldValue) {
                        if ($fieldValue['term'] && count($fieldValue['term']) > 0) {
                            if ($fieldValue['id'] && count($fieldValue['id']) > 0) {
                                $ids = $fieldValue['id'];
                                foreach($ids as $id) {
                                    if($id['source'] == $this->field) {
                                        $authority = $this->getAuthority($id);
                                        if (!array_key_exists($fieldValue['term'][0], $termsWithId)) {
                                            $termsWithId[$fieldValue['term'][0]] = array(array('id' => $id['id'], 'authority' => $authority));
                                        } else {
                                            $isIn = false;
                                            foreach($termsWithId[$fieldValue['term'][0]] as $knownId) {
                                                if($knownId['id'] === $id['id']) {
                                                    $isIn = true;
                                                    break;
                                                }
                                            }
                                            if (!$isIn) {
                                                $termsWithId[$fieldValue['term'][0]][] = array('id' => $id['id'], 'authority' => $authority);
                                            }
                                        }
                                    }
                                }
                            } else {
                                if (!array_key_exists($fieldValue['term'][0], $termsWithoutId)) {
                                    $termsWithoutId[$fieldValue['term'][0]] = '';
                                }
                            }
                        }
                    }
                }
            }
        }

        $csvData = '';
        foreach($termsWithoutId as $term => $id) {
            $csvData .= PHP_EOL . '"' . $term . '","","ongekend"';
        }
        foreach($termsWithId as $term => $ids) {
            foreach($ids as $id) {
                $csvData .= PHP_EOL . '"' . $term . '","' . $id['id'] . '","' . $id['authority'] . '"';
            }
        }

        $label = RecordUtil::getFieldLabel($field, $this->dataDef);

        return $label . ',Persistente ID,Authority' . $csvData;
    }

    private function ambigObjectNamePiechart()
    {
        return $this->ambigtermPie('object_name');
    }

    private function ambigObjectNameBarchart()
    {
        return $this->ambigtermBar('object_name');
    }

    private function ambigCategoryPiechart()
    {
        return $this->ambigtermPie('category');
    }

    private function ambigCategoryBarchart()
    {
        return $this->ambigtermBar('category');
    }

    private function ambigMainMotifPiechart()
    {
        return $this->ambigtermPie('main_motif');
    }

    private function ambigMainMotifBarchart()
    {
        return $this->ambigtermBar('main_motif');
    }

    private function ambigCreatorPiechart()
    {
        return $this->ambigtermPie('creator');
    }

    private function ambigCreatorBarchart()
    {
        return $this->ambigtermBar('creator');
    }

    private function ambigMaterialPiechart()
    {
        return $this->ambigtermPie('material');
    }

    private function ambigMaterialBarchart()
    {
        return $this->ambigtermBar('material');
    }

    private function ambigConceptPiechart()
    {
        return $this->ambigtermPie('displayed_concept');
    }

    private function ambigConceptBarchart()
    {
        return $this->ambigtermBar('displayed_concept');
    }

    private function ambigSubjectPiechart()
    {
        return $this->ambigtermPie('displayed_subject');
    }

    private function ambigSubjectBarchart()
    {
        return $this->ambigtermBar('displayed_subject');
    }

    private function ambigLocationPiechart()
    {
        return $this->ambigtermPie('displayed_location');
    }

    private function ambigLocationBarchart()
    {
        return $this->ambigtermBar('displayed_location');
    }

    private function ambigEventPiechart()
    {
        return $this->ambigtermPie('displayed_event');
    }

    private function ambigEventBarchart()
    {
        return $this->ambigtermBar('displayed_event');
    }
}
