
## Girdi Filtreleri ve Doğrulamalar

Girdi filtreleri ve doğrulamalar genellikle <kbd>kaydetme</kbd> operasyonlarında birlikte kullanılırlar. Bir girdi filtresinin çalışabilmesi için önce <kbd>App/ConfigProvider.php</kbd> dosyasına takip eden örnekte olduğu gibi tanımlanması gerekir.

```php [line-numbers] data-line="15"
declare(strict_types=1);

use Laminas\ServiceManager\AbstractFactory\ReflectionBasedAbstractFactory;

namespace App;

class ConfigProvider
{
    public function __invoke() : array
    {
        return [
            'dependencies' => $this->getDependencies(),
            'input_filters' => [
                'factories' => [
                    Filter\Companies\SaveFilter::class => Filter\Companies\SaveFilterFactory::class,
                    Filter\CollectionInputFilter::class => Container\CollectionInputFilterFactory::class,
                    Filter\ObjectInputFilter::class => Container\ObjectInputFilterFactory::class,
                ],
            ]
        ];
    }

    public function getDependencies() : array
    {
        return [
            'factories' => [

            ]
        ];
    }
}
```

### SaveFilterFactory

Filtre enjeksiyonu işleyicilerde olduğu gibi bir Factory sınıfı aracılığı ile gerçekleştirilir. Eğer filtre sınıfınıza bir sınıf göndermek istiyorsanız her bir filtre sınıfı için aşağıdaki gibi bir Facyory sınıfı tanımlamalısınız.

<kbd>src/App/Filter/Companies/SaveFilterFactory.php</kdd>

```php
<?php
declare(strict_types=1);

namespace App\Filter\Companies;

use Psr\Container\ContainerInterface;
use Laminas\Db\Adapter\AdapterInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class SaveFilterFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new SaveFilter($container->get(AdapterInterface::class));
    }
}
```

### SaveFilter

Aşağıdaki örnekte <b>Companies</b> modülüne ait <b>dizi</b> ve <b>nesne</b> verileri içermeyen basit bir girdi verisi doğrulanıyor.

<kbd>src/App/Filter/Companies/SaveFilter.php</kdd>

```php
<?php
namespace App\Filter\Companies;

use App\Filter\InputFilter;
use App\Filter\Utils\ToDate;
use App\Validator\Db\RecordExists;
use App\Validator\Db\NoRecordExists;
use Laminas\Validator\Date;
use Laminas\Filter\StringTrim;
use Laminas\Validator\Uuid;
use Laminas\Validator\StringLength;
use Laminas\Db\Adapter\AdapterInterface;

class SaveFilter extends InputFilter
{
    public function __construct(AdapterInterface $adapter)
    {
        $this->adapter = $adapter;
    }

    public function setInputData(array $data)
    {
        $this->add([
            'name' => 'id',
            'required' => true,
            'validators' => [
                ['name' => Uuid::class],
                [
                    'name' => HTTP_METHOD == 'POST' ? NoRecordExists::class : RecordExists::class,
                    'options' => [
                        'table'   => 'companies',
                        'field'   => 'companyId',
                        'adapter' => $this->adapter,
                    ]
                ]
            ],
        ]);
        $this->add([
            'name' => 'companyName',
            'required' => true,
            'filters' => [
                ['name' => StringTrim::class],
            ],
            'validators' => [
                [
                    'name' => StringLength::class,
                    'options' => [
                        'encoding' => 'UTF-8',
                        'min' => 3,
                        'max' => 160,
                    ],
                ],
            ],
        ]);
        $this->add([
            'name' => 'companyShortName',
            'required' => true,
            'filters' => [
                ['name' => StringTrim::class],
            ],
            'validators' => [
                [
                    'name' => StringLength::class,
                    'options' => [
                        'encoding' => 'UTF-8',
                        'min' => 2,
                        'max' => 60,
                    ],
                ],
            ],
        ]);
        $this->add([
            'name' => 'taxOffice',
            'required' => false,
            'filters' => [
                ['name' => StringTrim::class],
            ],
            'validators' => [
                [
                    'name' => StringLength::class,
                    'options' => [
                        'encoding' => 'UTF-8',
                        'min' => 3,
                        'max' => 100,
                    ],
                ],
            ],
        ]);
        $this->add([
            'name' => 'taxNumber',
            'required' => false,
            'filters' => [
                ['name' => StringTrim::class],
            ],
            'validators' => [
                [
                    'name' => StringLength::class,
                    'options' => [
                        'encoding' => 'UTF-8',
                        'min' => 3,
                        'max' => 60,
                    ],
                ],
            ],
        ]);
        $this->add([
            'name' => 'address',
            'required' => false,
            'filters' => [
                ['name' => StringTrim::class],
            ],
            'validators' => [
                [
                    'name' => StringLength::class,
                    'options' => [
                        'encoding' => 'UTF-8',
                        'min' => 3,
                        'max' => 255,
                    ],
                ],
            ],
        ]);
        $this->add([
            'name' => 'createdAt',
            'filters' => [
                ['name' => ToDate::class],
            ],
            'required' => false,
            'validators' => [
                [
                    'name' => Date::class,
                    'options' => [
                        'format' => 'Y-m-d',
                        'strict' => false,
                    ]
                ],
            ],
        ]);
        // Set input data
        //
        $this->setData($data);
    }
}
```

Takip eden bağlantıları gezerek daha fazla bilgi elde edebilirsiniz.

* <a href="https://docs.laminas.dev/laminas-inputfilter/">https://docs.laminas.dev/laminas-inputfilter/</a>
* <a href="https://docs.laminas.dev/laminas-validator/">https://docs.laminas.dev/laminas-validator/</a>


### DeleteFilter

Aşağıdaki örnekte <b>Companies</b> modülüne ait silme işlemlerinde <b>id</b> değerinin doğrulaması gösteriliyor. Silme doğrulama işleminde veritabanı ile bağlantı kurukarak mevcut şirketler içerisinde gönderilen id ile bir şirketin var olup olmadığı kontrol ediliyor.

<kbd>src/App/Filter/Companies/DeleteFilter.php</kdd>

```php
<?php
declare(strict_types=1);

namespace App\Filter\Companies;

use App\Filter\InputFilter;
use Laminas\Validator\Uuid;
use Laminas\Validator\Db\RecordExists;
use Laminas\Db\Adapter\AdapterInterface;

class DeleteFilter extends InputFilter
{
    public function __construct(AdapterInterface $adapter)
    {
        $this->adapter = $adapter;
    }

    public function setInputData(array $data)
    {
        $this->add([
            'name' => 'id',
            'required' => true,
            'validators' => [
                ['name' => Uuid::class],
                [
                    'name' => RecordExists::class,
                    'options' => [
                        'table'   => 'companies',
                        'field'   => 'companyId',
                        'adapter' => $this->adapter,
                    ]
                ]
            ],
        ]);
       
        $this->setData($data);
    }
}
```

### Create ve Update İşleyicileri

Takip eden örneklerde <kbd>App\Filter\CompanySaveFilter</kbd>, <b>CreateHandler</b> içerisindeki <b>create</b> ve <b>update</b> metotlarında kullanılıyor.

<tab>
<title>CreateHandler|UpdateHandler</title>
<content>

<kbd>src/App/Handler/Companies/CreateHandler.php</kdd>
```php
<?php
declare(strict_types=1);

namespace App\Handler\Companies;

use App\Model\CompanyModel;
use App\Schema\Companies\CompanySave;
use App\Filter\Companies\SaveFilter;
use Olobase\Mezzio\DataManagerInterface;
use Olobase\Mezzio\Error\ErrorWrapperInterface as Error;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class CreateHandler implements RequestHandlerInterface
{
    public function __construct(
        private CompanyModel $companySaveModel,
        private DataManagerInterface $dataManager,
        private SaveFilter $filter,
        private Error $error,
    ) 
    {
        $this->companySaveModel = $companySaveModel;
        $this->dataManager = $dataManager;
        $this->error = $error;
        $this->filter = $filter;
    }
    
    /**
     * @OA\Post(
     *   path="/companies/create",
     *   tags={"Companies"},
     *   summary="Create a new company",
     *   operationId="companies_create",
     *
     *   @OA\RequestBody(
     *     description="Create new Company",
     *     @OA\JsonContent(ref="#/components/schemas/CompanySave"),
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Successful operation",
     *   ),
     *   @OA\Response(
     *      response=400,
     *      description="Bad request, returns to validation errors"
     *   )
     *)
     **/
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->filter->setInputData($request->getParsedBody());
        $data = array();
        $response = array();
        if ($this->filter->isValid()) {
            $this->dataManager->setInputFilter($this->filter);
            $data = $this->dataManager->getSaveData(CompanySave::class, 'companies');
            $this->companySaveModel->create($data);
        } else {
            return new JsonResponse($this->error->getMessages($this->filter), 400);
        }
        return new JsonResponse($response);     
    }
}
```
<tcol>
<kbd>src/App/Handler/Companies/UpdateHandler.php</kdd>

```php
<?php
declare(strict_types=1);

namespace App\Handler\Companies;

use App\Model\CompanyModel;
use App\Schema\Companies\CompanySave;
use App\Filter\Companies\SaveFilter;
use Olobase\Mezzio\DataManagerInterface;
use Olobase\Mezzio\Error\ErrorWrapperInterface as Error;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class UpdateHandler implements RequestHandlerInterface
{
    public function __construct(
        private CompanyModel $companyModel,
        private DataManagerInterface $dataManager,
        private SaveFilter $filter,
        private Error $error,
    ) 
    {
        $this->companyModel = $companyModel;
        $this->dataManager = $dataManager;
        $this->error = $error;
        $this->filter = $filter;
    }
    
    /**
     * @OA\Put(
     *   path="/companies/update/{companyId}",
     *   tags={"Companies"},
     *   summary="Update company",
     *   operationId="companies_update",
     *
     *   @OA\Parameter(
     *       name="companyId",
     *       in="path",
     *       required=true,
     *       @OA\Schema(
     *           type="string",
     *       ),
     *   ),
     *   @OA\RequestBody(
     *     description="Update Company",
     *     @OA\JsonContent(ref="#/components/schemas/CompanySave"),
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Successful operation",
     *   ),
     *   @OA\Response(
     *      response=400,
     *      description="Bad request, returns to validation errors"
     *   )
     *)
     **/
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->filter->setInputData($request->getParsedBody());
        $data = array();
        $response = array();
        if ($this->filter->isValid()) {
            $this->dataManager->setInputFilter($this->filter);
            $data = $this->dataManager->getSaveData(CompanySave::class, 'companies');
            $this->companyModel->update($data);
        } else {
            return new JsonResponse($this->error->getMessages($this->filter), 400);
        }
        return new JsonResponse($response);   
    }
}
```
</content>
</tab>


## Nesne Türleri

Http başlığında <b>dizi</b> türünde bir veri gönderilmek istiyorsak, takip eden örnekte oluduğu gibi <b>employeePersonal</b> nesne olarak yazılmalıdır. 

<tab>
<title>Swagger|Json|Php</title>
<content>

```php
/**
*  @var object
*  @OA\Property(
*      @OA\Property(
*          property="militaryStatusId",
*          type="string",
*      ),
*      @OA\Property(
*          property="militaryStartDate",
*          type="string",
*      ),
*      @OA\Property(
*          property="militaryEndDate",
*          type="string",
*      ),
*      @OA\Property(
*          property="marialStatusId",
*          type="string",
*      ),
*  );
*/
public $employeePersonal;
```
<tcol>

```json
{
  "id": "string",
  "name": "string",
  "surname": "string",
  "employeePersonal": {
    "militaryStatusId": "string",
    "militaryStartDate": "string",
    "militaryEndDate": "string",
    "marialStatusId": "string"
  }
}
```
<tcol>
    
```php
[
    "id": "string",
    "name": "string",
    "surname": "string",
    "employeePersonal": [
        "militaryStatusId": "string",
        "militaryStartDate": "string",
        "militaryEndDate": "string",
        "marialStatusId": "string"
    ],
]
```
</content>
</tab>


### Nesneleri Filtrelemek (ObjectInputFilter)

<kbd>Employees\SaveFilter</kbd> sınıfı içerisinde, <kbd>App\Filter\ObjectInputFilter</kbd> ile <b>EmployeePersonal</b> nesnesi aşağıdaki gibi tanımlanabilir.

```php
namespace App\Filter\Employees;

use Laminas\Validator\Uuid;
use Laminas\Validator\Date;
use Laminas\Validator\InArray;
use Laminas\Validator\StringLength;
use Laminas\Validator\Db\RecordExists;
use Laminas\Validator\Db\NoRecordExists;
use Laminas\Db\Adapter\AdapterInterface;
use Laminas\InputFilter\InputFilterPluginManager;

class SaveFilter extends InputFilter
{
    public function __construct(
        AdapterInterface $adapter,
        InputFilterPluginManager $filter
    )
    {
        $this->filter = $filter;
        $this->adapter = $adapter;
    }

    public function setInputData(array $data)
    {
        $this->add([
            'name' => 'id',
            'required' => true,
            'validators' => [
                ['name' => Uuid::class],
                [
                    'name' => HTTP_METHOD == 'POST' ? NoRecordExists::class : RecordExists::class,
                    'options' => [
                        'table'   => 'employees',
                        'field'   => 'employeeId',
                        'adapter' => $this->adapter,
                    ]
                ]
            ],
        ]);
        $this->add([
            'name' => 'name',
            'required' => true,
            'validators' => [
                [
                    'name' => StringLength::class,
                    'options' => [
                        'encoding' => 'UTF-8',
                        'min' => 2,
                        'max' => 60,
                    ],
                ],
            ],
        ]);
        $this->add([
            'name' => 'surname',
            'required' => true,
            'validators' => [
                [
                    'name' => StringLength::class,
                    'options' => [
                        'encoding' => 'UTF-8',
                        'min' => 2,
                        'max' => 60,
                    ],
                ],
            ],
        ]);
        
        // Employee personal "object filter"
        //
        $objectFilter = $this->filter->get(ObjectInputFilter::class);
        $objectFilter->add([
            'name' => 'militaryStatusId',
            'required' => false,
            'validators' => [
                [
                    'name' => InArray::class,
                    'options' => [
                        'haystack' => ['postponement','discharge','exempt'],
                    ],
                ],
            ],
        ]);
        $objectFilter->add([
            'name' => 'militaryStartDate',
            'required' => false,
            'validators' => [
                [
                    'name' => Date::class,
                    'options' => [
                        'format' => 'Y-m-d',
                        'strict' => true,
                    ]
                ],
            ],
        ]);
        $objectFilter->add([
            'name' => 'militaryEndDate',
            'required' => false,
            'validators' => [
                [
                    'name' => Date::class,
                    'options' => [
                        'format' => 'Y-m-d',
                        'strict' => true,
                    ]
                ],
            ],
        ]);
        $objectFilter->add([
            'name' => 'marialStatusId',
            'required' => false,
            'validators' => [
                [
                    'name' => InArray::class,
                    'options' => [
                        'haystack' => ['married','single'],
                    ],
                ],
            ],
        ]);
        $this->add($objectFilter, 'employeePersonal');
        
        // Set input data
        //
        $this->renderInputData($data);
    }
}
```

## Dizi Türleri

Eğer http başlığında <b>dizi</b> türünde bir veri gönderilmek istiyorsak, takip eden örnekte oluduğu gibi <b>employeeChildren</b> dizi olarak yazılmalıdır. 

<tab>
<title>Swagger|Json|Php</title>
<content>

```php
/**
*  @var array
*  @OA\Property(
*      type="array",
*      @OA\Items(
*           @OA\Property(
*             property="childId",
*             type="string",
*           ),
*           @OA\Property(
*             property="childName",
*             type="string",
*           ),
*           @OA\Property(
*             property="childBirthdate",
*             type="string",
*           ),
*     ),
*  );
*/
public $employeeChildren;
```
<tcol>

```json
{
  "employeeId": "string",
  "name": "string",
  "surname": "string",
  "employeeChildren": [
      {
        "childId" : "string",
        "childNameSurname" : "string"
      }
  ]
}
```
<tcol>
    
```php
[
    "employeeId" =>  "string",
    "name" =>  "string",
    "surname" =>  "string",
    "employeeChildren" =>  [
        [
            "childId" => "string",
            "childNameSurname" =>  "string",
        ]
    ],
]
```
</content>
</tab>

### Dizileri Filtrelemek (CollectionInputFilter)

<kbd>EmployeeSaveFilter</kbd> sınıfı içerisinde, <kbd>App\Filter\CollectionInputFilter</kbd> ile <b>EmployeeChildren</b> dizi türü aşağıdaki tanımlanarak filtrelenebilir.

```php
namespace App\Filter\Employees;

use Laminas\Validator\Uuid;
use Laminas\Validator\Date;
use Laminas\Validator\InArray;
use Laminas\Validator\StringLength;
use Laminas\Validator\Db\RecordExists;
use Laminas\Validator\Db\NoRecordExists;
use Laminas\Db\Adapter\AdapterInterface;
use Laminas\InputFilter\InputFilterPluginManager;

class SaveFilter extends InputFilter
{
    public function __construct(
        AdapterInterface $adapter,
        CommonModel $commonModel,
        InputFilterPluginManager $filter
    )
    {
        $this->filter = $filter;
        $this->adapter = $adapter;
        $this->commonModel = $commonModel;
    }

    public function setInputData(array $data)
    {
        $this->add([
            'name' => 'id',
            'required' => true,
            'validators' => [
                ['name' => Uuid::class],
                [
                    'name' => HTTP_METHOD == 'POST' ? NoRecordExists::class : RecordExists::class,
                    'options' => [
                        'table'   => 'employees',
                        'field'   => 'employeeId',
                        'adapter' => $this->adapter,
                    ]
                ]
            ],
        ]);
        $this->add([
            'name' => 'name',
            'required' => true,
            'validators' => [
                [
                    'name' => StringLength::class,
                    'options' => [
                        'encoding' => 'UTF-8',
                        'min' => 2,
                        'max' => 60,
                    ],
                ],
            ],
        ]);
        $this->add([
            'name' => 'surname',
            'required' => true,
            'validators' => [
                [
                    'name' => StringLength::class,
                    'options' => [
                        'encoding' => 'UTF-8',
                        'min' => 2,
                        'max' => 60,
                    ],
                ],
            ],
        ]);

        // Employee children "array input filter"
        //
        $collection = $this->filter->get(CollectionInputFilter::class);
        $collectionFilter = $this->filter->get(InputFilter::class);
        $collectionFilter->add([
            'name' => 'childId',
            'required' => false,
            'validators' => [
                ['name' => Uuid::class],
            ],
        ]);
        $collectionFilter->add([
            'name' => 'childNameSurname',
            'required' => true,
            'validators' => [
                [
                    'name' => StringLength::class,
                    'options' => [
                        'encoding' => 'UTF-8',
                        'min' => 3,
                        'max' => 160,
                    ],
                ],
            ],
        ]);
        $collectionFilter->add([
            'name' => 'childBirthdate',
            'required' => false,
            'validators' => [
                [
                    'name' => Date::class,
                    'options' => [
                        'format' => 'Y-m-d',
                        'strict' => true,
                    ]
                ],
            ],
        ]);
        $collection->setInputFilter($collectionFilter);
        $this->add($collection, 'employeeChildren');

        // Set input data
        //
        $this->renderInputData($data);
    }
}
```