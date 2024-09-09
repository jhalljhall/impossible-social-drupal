import {Plugin} from 'ckeditor5/src/core';
import {toWidget, Widget} from 'ckeditor5/src/widget';
import SimpleAdsCommand from './simpleadscommand';

/**
 * SimpleAds editing functionality.
 */
export default class SimpleAdsEditing extends Plugin {

  /**
   * @inheritdoc
   */
  static get requires() {
    return [Widget];
  }

  /**
   * @inheritdoc
   */
  init() {
    this.attrs = {
      SimpleAdsGroup: 'data-group',
      SimpleAdsRotationType: 'data-rotation-type',
      SimpleAdsRandomLimit: 'data-random-limit',
      SimpleAdsImpressions: 'data-impressions',
    };
    const options = this.editor.config.get('SimpleAds');
    if (!options) {
      return;
    }
    const {previewURL, themeError} = options;
    this.previewUrl = previewURL;
    this.themeError =
      themeError ||
      `
      <p>${Drupal.t(
        'An error occurred while trying to preview the SimpleAds. Please save your work and reload this page.',
      )}<p>
    `;

    this._defineSchema();
    this._defineConverters();

    this.editor.commands.add(
      'SimpleAds',
      new SimpleAdsCommand(this.editor),
    );
  }

  /**
   * Fetches the preview.
   */
  async _fetchPreview(modelElement) {
    const query = {
      group: modelElement.getAttribute('SimpleAdsGroup'),
      rotation: modelElement.getAttribute('SimpleAdsRotationType'),
      multiple_random_limit: modelElement.getAttribute('SimpleAdsRandomLimit'),
      rotation_impressions: modelElement.getAttribute('SimpleAdsImpressions'),
    };
    const response = await fetch(
      `${this.previewUrl}?${new URLSearchParams(query)}`
    );
    if (response.ok) {
      return await response.text();
    }

    return this.themeError;
  }

  /**
   * Registers SimpleAds as a block element in the DOM converter.
   */
  _defineSchema() {
    const schema = this.editor.model.schema;
    schema.register('SimpleAds', {
      allowWhere: '$block',
      isObject: true,
      isContent: true,
      isBlock: true,
      allowAttributes: Object.keys(this.attrs),
    });
    this.editor.editing.view.domConverter.blockElements.push('simpleads');
  }

  /**
   * Defines handling of drupal media element in the content lifecycle.
   *
   * @private
   */
  _defineConverters() {
    const conversion = this.editor.conversion;

    conversion
      .for('upcast')
      .elementToElement({
        model: 'SimpleAds',
        view: {
          name: 'simpleads',
        },
      });

    conversion
      .for('dataDowncast')
      .elementToElement({
        model: 'SimpleAds',
        view: {
          name: 'simpleads',
        },
      });
    conversion
      .for('editingDowncast')
      .elementToElement({
        model: 'SimpleAds',
        view: (modelElement, {writer}) => {
          const container = writer.createContainerElement('figure');
          return toWidget(container, writer, {
            label: Drupal.t('SimpleAds'),
          });

        },
      })
      .add((dispatcher) => {
        const converter = (event, data, conversionApi) => {
          const viewWriter = conversionApi.writer;
          const modelElement = data.item;
          const container = conversionApi.mapper.toViewElement(data.item);
          const SimpleAds = viewWriter.createRawElement('div', {
            'data-simpleads-preview': 'loading',
            'class': 'simpleads-preview'
          });
          viewWriter.insert(viewWriter.createPositionAt(container, 0), SimpleAds);
          this._fetchPreview(modelElement).then((preview) => {
            if (!SimpleAds) {
              return;
            }
            this.editor.editing.view.change((writer) => {
              const SimpleAdsPreview = writer.createRawElement(
                'div',
                {'class': 'simpleads-preview', 'data-simpleads-preview': 'ready'},
                (domElement) => {
                  domElement.innerHTML = preview;
                },
              );
              writer.insert(writer.createPositionBefore(SimpleAds), SimpleAdsPreview);
              writer.remove(SimpleAds);
            });
          });
        };
        dispatcher.on('attribute:SimpleAdsGroup:SimpleAds', converter);
        return dispatcher;
      });

    Object.keys(this.attrs).forEach((modelKey) => {
      const attributeMapping = {
        model: {
          key: modelKey,
          name: 'SimpleAds',
        },
        view: {
          name: 'simpleads',
          key: this.attrs[modelKey],
        },
      };
      conversion.for('dataDowncast').attributeToAttribute(attributeMapping);
      conversion.for('upcast').attributeToAttribute(attributeMapping);
    });
  }

  /**
   * @inheritdoc
   */
  static get pluginName() {
    return 'SimpleAdsEditing';
  }
}
