<?php


namespace Pim\Bundle\DataGeneratorBundle\Generator\Product;

use Faker;
use Pim\Bundle\CatalogBundle\Model\AttributeInterface;
use Pim\Bundle\CatalogBundle\Model\FamilyInterface;
use Pim\Bundle\CatalogBundle\Repository\AttributeRepositoryInterface;

/**
 * Build a raw product (ie: as an array) with random data.
 *
 * @author    Julien Janvier <jjanvier@akeneo.com>
 * @copyright 2014 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class ProductRawBuilder
{
    const CATEGORY_FIELD = 'categories';

    /** @var Faker\Generator */
    private $faker;

    /** @var ProductValueRawBuilder */
    private $valueBuilder;

    /** @var AttributeRepositoryInterface */
    private $attributeRepository;

    /** @var array */
    private $attributesByFamily;

    /** @var array */
    private $categoryCodes;

    /** @var string */
    private $identifierCode;

    public function __construct(ProductValueRawBuilder $valueBuilder, AttributeRepositoryInterface $attributeRepository)
    {
        $this->valueBuilder = $valueBuilder;
        $this->attributeRepository = $attributeRepository;
        $this->attributesByFamily = [];
    }

    /**
     * @param Faker\Generator $faker
     */
    public function setFakerGenerator(Faker\Generator $faker)
    {
        $this->faker = $faker;
    }

    /**
     * Build a raw product with its basic fields.
     *
     * @param FamilyInterface $family
     * @param string          $id
     * @param string          $groups
     *
     * @return array
     */
    public function buildBaseProduct(FamilyInterface $family, $id, $groups)
    {
        $product = [];
        $product[$this->getIdentifierCode()] = $id;
        $product['family'] = $family->getCode();
        $product['groups'] = $groups;

        return $product;
    }

    /**
     * Modify the $product to fill some random attributes
     *
     * @param FamilyInterface $family
     * @param array     $product
     * @param array     $forcedAttributes
     * @param int       $nbAttr
     * @param int       $nbAttrDeviation
     */
    public function fillInRandomAttributes(
        FamilyInterface $family,
        array &$product,
        array $forcedAttributes,
        $nbAttr,
        $nbAttrDeviation
    ) {
        $randomNbAttr = $this->getRandomAttributesCount(
            $family,
            $nbAttr,
            $nbAttrDeviation
        );
        $attributes = $this->getRandomAttributesFromFamily($family, $randomNbAttr);

        foreach ($attributes as $attribute) {
            $valueData = $this->generateValue($attribute, $forcedAttributes);
            $product   = array_merge($product, $valueData);
        }
    }

    /**
     * Modify the $product to fill in its mandatory attributes.
     *
     * @param FamilyInterface $family
     * @param array     $product
     * @param array     $forcedAttributes
     * @param array     $mandatoryAttributes
     */
    public function fillInMandatoryAttributes(
        FamilyInterface $family,
        array &$product,
        array $forcedAttributes,
        array $mandatoryAttributes
    ) {
        foreach ($mandatoryAttributes as $attribute) {
            if (isset($this->attributesByFamily[$family->getCode()][$attribute])) {
                $attribute = $this->attributesByFamily[$family->getCode()][$attribute];
                $valueData = $this->generateValue($attribute, $forcedAttributes);
                $product   = array_merge($product, $valueData);
            }
        }
    }

    /**
     * Modify the $product to fill in some random categories.
     *
     * @param array $product
     * @param int   $categoriesCount
     */
    public function fillInRandomCategories(array &$product, $categoriesCount)
    {
        $categories = $this->faker->randomElements($this->getCategoryCodes(), $categoriesCount);

        $product[self::CATEGORY_FIELD] = implode(',', $categories);
    }

    /**
     * @param AttributeInterface $attribute
     * @param array              $forceProperties
     *
     * @return array
     */
    private function generateValue(AttributeInterface $attribute, array $forceProperties)
    {
        if (isset($forceProperties[$attribute->getCode()])) {
            return [$attribute->getCode() => $forceProperties[$attribute->getCode()]];
        }

        return $this->valueBuilder->build($attribute);
    }

    /**
     * Get random attributes from the family
     *
     * @param FamilyInterface $family
     * @param int             $count
     *
     * @return AttributeInterface[]
     */
    private function getRandomAttributesFromFamily(FamilyInterface $family, $count)
    {
        return $this->faker->randomElements($this->getAttributesFromFamily($family), $count);
    }

    /**
     * Returns the number of attributes to set.
     *
     * @param FamilyInterface $family
     * @param int             $nbAttrBase
     * @param int             $nbAttrDeviation
     *
     * @return int
     */
    private function getRandomAttributesCount(FamilyInterface $family, $nbAttrBase, $nbAttrDeviation)
    {
        if ($nbAttrBase > 0) {
            if ($nbAttrDeviation > 0) {
                $nbAttr = $this->faker->numberBetween(
                    $nbAttrBase - round($nbAttrDeviation / 2),
                    $nbAttrBase + round($nbAttrDeviation / 2)
                );
            } else {
                $nbAttr = $nbAttrBase;
            }
        }
        $familyAttrCount = count($this->getAttributesFromFamily($family));

        if (!isset($nbAttr) || $nbAttr > $familyAttrCount) {
            $nbAttr = $familyAttrCount;
        }

        return $nbAttr;
    }

    /**
     * Get non-identifier attribute from family
     *
     * @param FamilyInterface $family
     *
     * @return AttributeInterface[]
     */
    private function getAttributesFromFamily(FamilyInterface $family)
    {
        $familyCode = $family->getCode();

        if (!isset($this->attributesByFamily[$familyCode])) {
            $this->attributesByFamily[$familyCode] = [];

            $attributes = $family->getAttributes();
            foreach ($attributes as $attribute) {
                if ($attribute->getCode() !== $this->getIdentifierCode()) {
                    $this->attributesByFamily[$familyCode][$attribute->getCode()] = $attribute;
                }
            }
        }

        return $this->attributesByFamily[$familyCode];
    }

    /**
     * Get all categories that are not root
     *
     * @return string[]
     */
    private function getCategoryCodes()
    {
        if (null === $this->categoryCodes) {
            $this->categoryCodes = [];
            $categories = $this->categoryRepository->findAll();
            foreach ($categories as $category) {
                if (null !== $category->getParent()) {
                    $this->categoryCodes[] = $category->getCode();
                }
            }
        }

        return $this->categoryCodes;
    }

    /**
     * @return string
     */
    private function getIdentifierCode()
    {
        if (null === $this->identifierCode) {
            $this->identifierCode = $this->attributeRepository->getIdentifierCode();
        }

        return $this->identifierCode;
    }
}
