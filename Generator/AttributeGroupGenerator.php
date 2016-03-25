<?php

namespace Pim\Bundle\DataGeneratorBundle\Generator;

use Faker\Factory;
use Faker\Generator;
use Pim\Bundle\CatalogBundle\Entity\AttributeGroup;
use Pim\Bundle\DataGeneratorBundle\Writer\WriterInterface;
use Pim\Component\Catalog\Model\LocaleInterface;
use Symfony\Component\Console\Helper\ProgressHelper;
use Symfony\Component\Yaml;

/**
 * Class AttributeGroupGenerator
 *
 * @author    Damien Carcel (https://github.com/damien-carcel)
 * @copyright 2015 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class AttributeGroupGenerator implements GeneratorInterface
{
    const ATTR_GROUP_CODE_PREFIX = 'attr_gr_';

    const ATTRIBUTE_GROUP_FILENAME = 'attribute_groups.csv';

    /** @var WriterInterface */
    protected $writer;

    /** @var array */
    protected $attributeGroups;

    /** @var Generator */
    protected $faker;

    /** @var LocaleInterface[] */
    protected $locales;

    /**
     * @param WriterInterface $writer
     */
    public function __construct(WriterInterface $writer)
    {
        $this->writer = $writer;
    }

    /**
     * {@inheritdoc}
     */
    public function generate(array $globalConfig, array $config, ProgressHelper $progress, array $options = [])
    {
        $this->locales = $options['locales'];
        $count         = (int)$config['count'];

        $this->faker = Factory::create();
        if (isset($globalConfig['seed'])) {
            $this->faker->seed($globalConfig['seed']);
        }

        $this->attributeGroups = [];

        for ($i = 0; $i < $count; $i++) {
            $attributeGroup = [];
            $code = self::ATTR_GROUP_CODE_PREFIX.$i;

            $attributeGroup['code']      = $code;
            $attributeGroup['sortOrder'] = $this->faker->numberBetween(1, 10);
            $attributeGroup['labels']    = $this->getLocalizedRandomLabels();

            $this->attributeGroups[$code] = $attributeGroup;
            $progress->advance();
        }

        $normalizedGroups = $this->normalizeAttributeGroups($this->attributeGroups);

        $this->writer
            ->setFilename($globalConfig['output_dir'] . '/' . static::ATTRIBUTE_GROUP_FILENAME)
            ->setData($normalizedGroups)
            ->write();

        return $this;
    }

    /**
     * Return the generated attribute groups as AttributeGroup object.
     *
     * @return array
     */
    public function getAttributeGroups()
    {
        $attrGroupObjects = [];

        foreach ($this->attributeGroups as $code => $attributeGroup) {
            $attrGroupObject = new AttributeGroup();

            $attrGroupObject->setCode($code);

            $attrGroupObjects[$code] = $attrGroupObject;
        }

        return $attrGroupObjects;
    }

    /**
     * Get localized random labels
     *
     * @return array
     */
    protected function getLocalizedRandomLabels()
    {
        $labels = [];

        foreach ($this->locales as $locale) {
            $labels[$locale->getCode()] = $this->faker->sentence(2);
        }

        return $labels;
    }

    protected function normalizeAttributeGroups($groups)
    {
        $result = [];

        foreach ($groups as $group) {
            $normalizedGroup = [
                'code'       => $group['code'],
                'sort_order' => $group['sortOrder'],
            ];
            foreach ($group['labels'] as $locale => $label) {
                $normalizedGroup[sprintf('label-%s', $locale)] = $label;
            }
            $result[] = $normalizedGroup;
        }

        return $result;
    }
}
