<?php
namespace Snowcap\YamlFixturesBundle\YamlFixtures;

use Symfony\Component\Yaml\Yaml,
Symfony\Component\DependencyInjection\Container,
Doctrine\Common\CommonException as DoctrineException,
Doctrine\Common\Collections\ArrayCollection,
Doctrine\ORM\EntityManager,
Doctrine\Common\DataFixtures\AbstractFixture,
Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Symfony\Component\Validator\Validator;
use Symfony\Component\Validator\Mapping\ClassMetadataFactory;
use Symfony\Component\Validator\Mapping\Loader\StaticMethodLoader;
use Symfony\Component\Validator\ConstraintValidatorFactory;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Check if the passed array is an associative array
 *
 * @param array $value
 * @return bool
 */
function is_assoc($value)
{
    return
        is_array($value) &&
        count(array_filter(array_keys($value), function($key)
        {
            return is_string($key);
        })) !== 0;
}

abstract class AbstractYamlFixture extends AbstractFixture implements OrderedFixtureInterface, ContainerAwareInterface
{
    /**
     * @var \Doctrine\ORM\EntityManager
     *
     */
    protected $manager;

    /**
     * @var \Symfony\Component\DependencyInjection\ContainerInterface
     */
    protected $container;

    /**
     * Process a fixture value according to its type and / or format
     *
     * @throws \Exception|DoctrineException
     * @param string $field
     * @param mixed $value
     * @param object $entity
     * @return mixed
     */
    protected function processValue($field, $value, $entity)
    {
        // If $value is an associative array, create a new entity
        if (is_assoc($value)) {
            $metadata = $this->manager->getClassMetadata(get_class($entity))->getAssociationMapping($field);
            $processedValue = new $metadata['targetEntity'];
            $this->populateEntity($processedValue, null, $value);
        }
        // If $value is a regular array, perform recursive call on each of its items
        elseif (is_array($value)) {
            $processedValue = new ArrayCollection();
            foreach ($value as $singleValue) {
                $processedValue[] = $this->processValue($field, $singleValue, $entity);
            }
        }
        // If $value starts with a @ character, look for a reference
        elseif (strpos($value, '@') === 0) {
            $associatedIdentifier = substr($value, 1);
            if (!$this->getReference($associatedIdentifier)) {
                throw new DoctrineException(sprintf('Trying to reference non-existing fixture entity "%s"', $associatedIdentifier));
            }
            $processedValue = $this->getReference($associatedIdentifier);
        }
        // If $value is a valid date
        elseif ($this->isDate($value)) {
            $processedValue = new \DateTime($value);
        }
        // Default treatment
        else {
            $processedValue = $this->decodeMarkdown($value);
        }
        return $processedValue;
    }

    /**
     * Populate the entity with the fixture data
     *
     * @param \Doctrine\ORM\EntityManager $manager
     * @param object $entity
     * @param string $entityIdentifier
     * @param array $values
     */
    protected function populateEntity($entity, $entityIdentifier, array $values)
    {
        foreach ($values as $field => $value) {
            $processedValue = $this->processValue($field, $value, $entity);
            call_user_func(array($entity, 'set' . Container::camelize($field)), $processedValue);
            if ($entityIdentifier !== null) {
                $this->setReference($entityIdentifier, $entity);
            }
        }
    }

    /**
     * Parse a YAML file and process its data
     *
     * @param string $path
     * @param string $entityShortcut
     * @param callback $callback
     */
    protected function loadYaml($path, $entityShortcut, $callback = null)
    {
        if (!file_exists($path)) {
            throw new \ErrorException(sprintf('No file found for path "%s"', $path));
        }
        $entries = Yaml::parse($path);
        foreach ($entries as $identifier => $data) {
            $entityName = $this->manager->getClassMetaData($entityShortcut)->getName();
            $entity = new $entityName();
            $this->populateEntity($entity, $identifier, $data);
            if (is_callable($callback)) {
                call_user_func($callback, $entity, $data);
            }
            $this->manager->persist($entity);
        }
        $this->manager->flush();
    }

    /**
     * Decode strings to avoid markdown formatting issues
     *
     * @param $string
     * @return string
     */
    protected function decodeMarkdown($string)
    {
        return preg_replace('/(?<=^|%)%/m', '#', $string);
    }

    protected function isDate($value)
    {
        $validator = $this->container->get('validator');/* @var Symfony\Component\Validator\Validator $validator */
        $violations = $validator->validateValue($value, new \Symfony\Component\Validator\Constraints\Date());
        return count($violations) === 0;
    }

    protected function isDateTime($value)
    {
        $validator = $this->container->get('validator');/* @var Symfony\Component\Validator\Validator $validator */
        $violations = $validator->validateValue($value, new \Symfony\Component\Validator\Constraints\DateTime());
        return count($violations) === 0;
    }

    /**
     * @param \Doctrine\Common\Persistence\ObjectManager $manager
     */
    public function load(ObjectManager $manager)
    {
        $this->manager = $manager;
        $this->loadYamlFiles();
    }



    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    abstract public function loadYamlFiles();
}