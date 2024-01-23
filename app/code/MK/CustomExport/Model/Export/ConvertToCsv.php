<?php
namespace MK\CustomExport\Model\Export;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Ui\Component\MassAction\Filter;
use Magento\Ui\Model\Export\MetadataProvider;
use Magento\Ui\Model\Export\ConvertToCsv as ConvertToCsvParent;
/**
 * Class ConvertToCsv
 */
class ConvertToCsv extends ConvertToCsvParent
{


    /**
     * @var DirectoryList
     */
    protected $directory;
    /**
     * @var MetadataProvider
     */
    protected $metadataProvider;
    /**
     * @var int|null
     */
    protected $pageSize = null;
    /**
     * @var Filter
     */
    protected $filter;
    /**
     * @var Product
     */
    /**
     * @var TimezoneInterface
     */
    private $timezone;
    /**
     * @param Filesystem $filesystem
     * @param Filter $filter
     * @param MetadataProvider $metadataProvider
     * @param int $pageSize
     * @throws FileSystemException
     */
    public function __construct(
        Filesystem $filesystem,
        Filter $filter,
        MetadataProvider $metadataProvider,
        TimezoneInterface $timezone,
        $pageSize = 200
    ) {
        $this->filter = $filter;
        $this->directory = $filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
        $this->metadataProvider = $metadataProvider;
        $this->pageSize = $pageSize;
        parent::__construct($filesystem, $filter, $metadataProvider, $pageSize);
        $this->timezone = $timezone;
    }
    public function getCsvFile()
    {
        $writer = new \Zend_Log_Writer_Stream(BP . '/var/log/customExport3.log');
        $logger = new \Zend_Log();
        $logger->addWriter($writer);
    
        $component = $this->filter->getComponent();


        $processedOrders = [];


        $name = md5(microtime());
        $file = 'export/'. $component->getName() . $name . '.csv';

        $this->filter->prepareComponent($component);
        $this->filter->applySelectionOnTargetProvider();
        $dataProvider = $component->getContext()->getDataProvider();
        //exit(get_class($dataProvider));
        $fields = $this->metadataProvider->getFields($component);

        $options = $this->metadataProvider->getOptions();

        $this->directory->create('export');
        $stream = $this->directory->openFile($file, 'w+');
        $stream->lock();
        $stream->writeCsv($this->metadataProvider->getHeaders($component));
        $i = 1;
        $searchCriteria = $dataProvider->getSearchCriteria()
            ->setCurrentPage($i)
            ->setPageSize($this->pageSize);
        $totalCount = (int) $dataProvider->getSearchResult()->getTotalCount();

        while ($totalCount > 0) {
    
    
            $searchCriteria = $dataProvider->getSearchCriteria()
                ->setCurrentPage($i)
                ->setPageSize($this->pageSize);
    
            $items = $dataProvider->getSearchResult()->getItems();
    
            foreach ($items as $item) {
                $incrementId = $item->getIncrementId();
    
                // Check if the increment ID has been processed before
                if (in_array($incrementId, $processedOrders)) {
                    continue; // Skip processing if already processed
                }
    
                // Mark the increment ID as processed
                $processedOrders[] = $incrementId;
    
                $logger->info(json_encode($incrementId));
    
                if ($component->getName() == 'sales_order_grid') {
                    $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                    $order = $objectManager->create('Magento\Sales\Model\Order')->load($item->getEntityId());
                    $items123 = $order->getAllItems();
                    $skuArray = [];
    
                    foreach ($items123 as $key => $item1) {
                        $skuArray[] = $item1->getSku();
                    }
                    $export_status = implode(", ", $skuArray);
    
                    $item->setSku($export_status);
                }
    
                $this->metadataProvider->convertDate($item, $component->getName());
                $stream->writeCsv($this->metadataProvider->getRowData($item, $fields, $options));
            }
            $searchCriteria->setCurrentPage(++$i);
            $totalCount = $totalCount - $this->pageSize;
    
    }
    
        $stream->unlock();
        $stream->close();
    
        return [
            'type' => 'filename',
            'value' => $file,
            'rm' => true  // can delete file after use
        ];
    }
    


}