<?php
namespace App\Command;

use App\Document\Provider;
use App\Document\Record;
use App\Document\CompletenessReport;
use App\Document\CompletenessTrend;
use App\Document\FieldReport;
use App\Document\FieldTrend;
use App\Util\RecordUtil;
use DateTime;
use Phpoaipmh\Endpoint;
use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[AsCommand(
    name: 'app:fetch-data',
    description: 'Fetches all data from the Datahub and stores the relevant information in a local database.',
)]
class FetchDataCommand extends Command
{
    private ParameterBagInterface $parameterBag;
    private readonly DocumentManager $documentManager;

    private readonly string $datahubUrl;
    private readonly string $datahubNamespace;
    private readonly string $datahubMetadataPrefix;
    private readonly array $dataDefinition;
    private readonly array $providersDefinition;
    private readonly array $termsWithIds;

    public function __construct(ParameterBagInterface $parameterBag, DocumentManager $documentManager)
    {
        parent::__construct();
        $this->parameterBag = $parameterBag;
        $this->documentManager = $documentManager;
    }

    protected function configure(): void
    {
        $this
            ->setName('app:fetch-data')
            ->addArgument("url", InputArgument::OPTIONAL, "The URL of the Datahub")
            ->setDescription('Fetches all data from the Datahub and stores the relevant information in a local database.')
            ->setHelp('This command fetches all data from the Datahub and stores the relevant information in a local database.\nOptional parameter: the URL of the datahub. If the URL equals "skip", it will not fetch data and use whatever is currently in the database.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $url = $input->getArgument('url');
        $skip = false;

        if (!$url) {
            $url = $this->parameterBag->get('datahub_url');
        } elseif ($url === 'skip') {
            $skip = true;
        }

        $verbose = $input->getOption('verbose');

        $namespace = $this->parameterBag->get('datahub.namespace');
        $metadataPrefix = $this->parameterBag->get('datahub.metadataprefix');
        $dataDef = $this->parameterBag->get('data_definition');
        $providerDef = $this->parameterBag->get('providers');

        $providers = null;
        if(!$skip) {
            // Build the OAI-PMH client
            $myEndpoint = Endpoint::build($url);

            // List the OAI-PMH records
            $recs = $myEndpoint->listRecords($metadataPrefix);

            // Remove all current data in the local database
            $this->documentManager->getDocumentCollection(Record::class)->deleteMany([]);

            $providers = array();
            $recordIds = array();
            $i = 0;
            foreach ($recs as $rec) {
                $i++;
                $data = $rec->metadata->children($namespace, true);

                //Fetch the data from this record based on data_definition in dashboard.yml
                $fetchedData = $this->fetchData($dataDef, $namespace, $data, $providers, $providerDef, $verbose);

                // Create & store a new record based on this data
                if(array_key_exists('provider', $fetchedData)) {
                    if(count($fetchedData['provider']) > 0) {
                        $providerId = $fetchedData['provider'][0];
                        $record = new Record($providerId, $fetchedData);
                        $this->documentManager->persist($record);

                        if (!array_key_exists($providerId, $recordIds)) {
                            $recordIds[$providerId] = array();
                        }
                        $recordIds[$providerId][] = $record->getId();
                    }
                }
                if($i % 100 === 0) {
                    $this->documentManager->flush();
                    $this->documentManager->clear();
                    if($verbose && $i % 1000 === 0) {
                        echo 'At ' . $i . PHP_EOL;
                    }
                }
            }
            $this->documentManager->flush();
            $this->documentManager->clear();
            $this->storeProviders($providers);
        }
        else {
            $providers = $this->documentManager->getRepository(Provider::class)->findAll();
        }

        // Generate & store the report & trends
        $this->generateAndStoreReport($dataDef, $providers, $recordIds);

        return Command::SUCCESS;
    }

    private function fetchData($dataDef, $namespace, $data, &$providers, $providerDef, $verbose)
    {
        $result = array();
        foreach ($dataDef as $key => $value) {
            if(RecordUtil::excludeKey($key)) {
                continue;
            }
            if(array_key_exists('xpath', $value)) {
                $xpath = $this->buildXpath($value['xpath'], $namespace);
                $res = $data->xpath($xpath);
                if ($res) {
                    $arr = array();
                    foreach ($res as $resChild) {
                        $child = (string)$resChild;
                        if($key === 'id') {
                            $idArr = array('id' => $child);
                            $attributes = $resChild->attributes($namespace, true);
                            if ($attributes) {
                                foreach ($attributes as $attributeKey => $attributeValue) {
                                    $idArr[(string)$attributeKey] = (string)$attributeValue;
                                }
                            }
                            $arr[] = $idArr;
                        } elseif($key === 'term') {
                            $termArr = array('term' => $child);
                            $attributes = $resChild->attributes($namespace, true);
                            if ($attributes) {
                                foreach ($attributes as $attributeKey => $attributeValue) {
                                    $termArr[(string)$attributeKey] = (string)$attributeValue;
                                }
                            }
                            $arr[] = $termArr;
                        }
                        else {
                            if (strlen($child) > 0 && strtolower($child) !== 'n/a') {
                                if ($key === 'provider') {
                                    $arr[] = $this->addToProviders($child, $providers, $providerDef, $verbose);
                                } else {
                                    $arr[] = $child;
                                }
                            }
                        }
                    }
                    $result[$key] = $arr;
                } else {
                    $result[$key] = null;
                }
            }
            elseif(array_key_exists('parent_xpath', $value)) {
                $xpath = $this->buildXpath($value['parent_xpath'], $namespace);
                $res = $data->xpath($xpath);
                if ($res) {
                    foreach($res as $r) {
                        $result[$key][] = $this->fetchData($value, $namespace, $r, $providers, $providerDef, $verbose);
                    }
                } else {
                    $result[$key] = null;
                }
            }
        }
        return $result;
    }

    // Build the xpath based on the provided namespace
    private function buildXpath($xpath, $namespace)
    {
        $xpath = str_replace('[@', '[@' . $namespace . ':', $xpath);
        $xpath = str_replace(' or @', ' or @' . $namespace . ':', $xpath);
        $xpath = preg_replace('/\[([^@])/', '[' . $namespace . ':${1}', $xpath);
        $xpath = preg_replace('/\/([^\/])/', '/' . $namespace . ':${1}', $xpath);
        if(strpos($xpath, '/') !== 0) {
            $xpath = $namespace . ':' . $xpath;
        }
        $xpath = 'descendant::' . $xpath;
        return $xpath;
    }

    // Add a newly found provider to the list of known data providers
    private function addToProviders($providerName, &$providers, $providerDef, $verbose)
    {
        foreach ($providers as $provider) {
            if ($provider->getName() === $providerName) {
                return $provider->getIdentifier();
            }
        }
        if(array_key_exists($providerName, $providerDef)) {
            $providerId = $providerDef[$providerName];
        } else {
            // Generate a new ID for this provider by removing non-alphanumeric characters and cutting off at 25 characters
            $providerId = preg_replace("/[^A-Za-z0-9 ]/", '', $providerName);
            while(strpos($providerId, '  ') > -1) {
                $providerId = str_replace('  ', ' ', $providerId);
            }
            $providerId = str_replace(' ', '_', $providerId);
            $providerId = strtolower($providerId);
            if(strlen($providerId) > 25) {
                $providerId = substr($providerId, 0, 25);
            }
        }

        $providers[] = new Provider($providerId, $providerName);
        if($verbose) {
            echo 'Provider added: ' . $providerName . PHP_EOL;
        }

        return $providerId;
    }

    private function storeProviders($providers)
    {
        $this->documentManager->getDocumentCollection(Provider::class)->deleteMany([]);
        foreach($providers as $provider) {
            $this->documentManager->persist($provider);
        }
        $this->documentManager->flush();
        $this->documentManager->clear();
    }

    private function getAllRecords($providerId, $field)
    {
        // Clear the document manager cache, otherwise it will just return old results with the wrong data
        $this->documentManager->flush();
        $this->documentManager->clear();

        $qb = $this->documentManager->createQueryBuilder(Record::class)->field('provider')->equals($providerId)->select('data.' . $field);
        $query = $qb->getQuery();
        $data = $query->execute();
        return $data;
    }

    private function unsetCompleteness(&$complete, $value, $id) {
        $class = $value['class'];
        if(array_key_exists($class, $complete)) {
            $exclude = false;
            if(array_key_exists('exclude', $value)) {
                if($value['exclude'] === true) {
                    $exclude = true;
                }
            }
            if(!$exclude) {
                $index = array_search($id, $complete[$class]);
                if($index !== false) {
                    unset($complete[$class][$index]);
                }
                // If minimum registration is not fulfilled, then basic registration isn't either
                if($value['class'] === 'minimum') {
                    $index = array_search($id, $complete['basic']);
                    if($index !== false) {
                        unset($complete['basic'][$index]);
                    }
                }
            }
        }
    }

    private function generateAndStoreReport($dataDef, $providers, $recordIds)
    {
        $this->documentManager->getDocumentCollection(CompletenessReport::class)->deleteMany([]);
        $this->documentManager->getDocumentCollection(FieldReport::class)->deleteMany([]);
        $this->documentManager->flush();
        $this->documentManager->clear();

        foreach($providers as $provider) {
            $providerId = $provider->getIdentifier();

            $fields = array(
                'minimum' => array(),
                'basic' => array(),
                'extended' => array(),
                'rights_data' => array(),
                'rights_work' => array(),
                'rights_digital_representation' => array()
            );

            $complete = array(
                'minimum' => array(),
                'basic' => array(),
                'rights_data' => array(),
                'rights_work' => array(),
                'rights_digital_representation' => array()
            );
            foreach($complete as $class => $arr) {
                foreach($recordIds[$providerId] as $recordId) {
                    $complete[$class][] = $recordId;
                }
            }

            $termIds = array();
            $termsWithIdFields = $this->parameterBag->get('terms_with_ids');
            foreach($termsWithIdFields as $field) {
                $termIds[$field] = array();
            }

            foreach ($dataDef as $field => $value) {
                if (array_key_exists('xpath', $value)) {
                    if(array_key_exists('class', $value)) {
                        $fields[$value['class']][$field] = array();
                        $allRecords = $this->getAllRecords($providerId, $field);
                        if($allRecords) {
                            foreach ($allRecords as $record) {
                                $data = $record->getData();
                                $remove = true;
                                if (array_key_exists($field, $data)) {
                                    if($data[$field] != null) {
                                        if(count($data[$field]) > 0) {
                                            $fields[$value['class']][$field][] = $record->getId();
                                            $remove = false;
                                        }
                                    }
                                }
                                if($remove) {
                                    $this->unsetCompleteness($complete, $value, $record->getId());
                                }
                            }
                        }
                    }
                }
                elseif (array_key_exists('parent_xpath', $value)) {
                    $allRecords = $this->getAllRecords($providerId, $field);
                    if($allRecords) {
                        foreach ($value as $subField => $v) {
                            if (RecordUtil::excludeKey($subField) || !array_key_exists('class', $v)) {
                                continue;
                            }
                            if (array_key_exists('xpath', $v)) {
                                $fields[$v['class']][$field . '/' . $subField] = array();
                                foreach ($allRecords as $record) {
                                    $data = $record->getData();
                                    $remove = true;
                                    if (array_key_exists($field, $data)) {
                                        if($data[$field] != null) {
                                            if(count($data[$field]) > 0) {
                                                $idAdded = false;
                                                foreach ($data[$field] as $fieldData) {
                                                    if (array_key_exists($subField, $fieldData)) {
                                                        if($fieldData[$subField] != null) {
                                                            if (count($fieldData[$subField]) > 0) {
                                                                if(!$idAdded) {
                                                                    $fields[$v['class']][$field . '/' . $subField][] = $record->getId();
                                                                    $idAdded = true;
                                                                }
                                                                $remove = false;

                                                                if ($subField === 'id') {
                                                                    // Consider record incomplete unless there is a purl id
                                                                    $remove = true;
                                                                    foreach ($fieldData[$subField] as $id) {
                                                                        if ($id['type'] === 'purl') {
                                                                            $remove = false;
                                                                            break;
                                                                        }
                                                                    }
                                                                }

                                                                // Link terms with purl id's
                                                                if ($subField === 'term' && array_key_exists($field, $termIds)) {
                                                                    $term = RecordUtil::getPreferredTerm($fieldData[$subField]);
                                                                    if ($term && array_key_exists('id', $fieldData)) {
                                                                        if (is_array($fieldData['id'])) {
                                                                            if (count($fieldData['id']) > 0 && !array_key_exists($term, $termIds[$field])) {
                                                                                $firstPurlId = null;
                                                                                foreach ($fieldData['id'] as $termId) {
                                                                                    if ($termId['type'] === 'purl') {
                                                                                        $firstPurlId = $termId['id'];
                                                                                        break;
                                                                                    }
                                                                                }
                                                                                if ($firstPurlId != null) {
                                                                                    $termIds[$field][$term] = $firstPurlId;
                                                                                }
                                                                            }
                                                                        }
                                                                    }
                                                                }
                                                            }
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                    if($remove) {
                                        $this->unsetCompleteness($complete, $v, $record->getId());
                                    }
                                }
                            }
                        }
                    }
                }
            }

            $completenessReport = new CompletenessReport();
            $completenessReport->setProvider($providerId);
            $completenessReport->setTotal(count($recordIds[$providerId]));
            $completenessReport->setMinimum(count($complete['minimum']));
            $completenessReport->setBasic(count($complete['basic']));
            $completenessReport->setRightsData(count($complete['rights_data']));
            $completenessReport->setRightsWork(count($complete['rights_work']));
            $completenessReport->setRightsDigitalRepresentation(count($complete['rights_digital_representation']));
            $this->documentManager->persist($completenessReport);

            $completenessTrend = new CompletenessTrend();
            $completenessTrend->setProvider($providerId);
            $completenessTrend->setTimestamp(new MongoDate());
            $completenessTrend->setTotal($completenessReport->getTotal());
            $completenessTrend->setMinimum($completenessReport->getMinimum());
            $completenessTrend->setBasic($completenessReport->getBasic());
            $completenessTrend->setRightsWork($completenessReport->getRightsWork());
            $completenessTrend->setRightsDigitalRepresentation($completenessReport->getRightsDigitalRepresentation());
            $completenessTrend->setRightsData($completenessReport->getRightsData());
            $this->documentManager->persist($completenessTrend);

            $fieldReport = new FieldReport();
            $fieldReport->setTotal($completenessReport->getTotal());
            $fieldReport->setProvider($providerId);
            $fieldReport->setMinimum($fields['minimum']);
            $fieldReport->setBasic($fields['basic']);
            $fieldReport->setExtended($fields['extended']);
            $this->documentManager->persist($fieldReport);

            $termsWithIds = array();
            foreach($termIds as $field => $terms) {
                $termsWithIds[$field] = count($terms);
            }
            $fieldTrend = new FieldTrend();
            $fieldTrend->setProvider($providerId);
            $fieldTrend->setTimestamp(new DateTime());
            $fieldTrend->setCounts($termsWithIds);
            $this->documentManager->persist($fieldTrend);

            $this->documentManager->flush();
            $this->documentManager->clear();
        }
    }
}
