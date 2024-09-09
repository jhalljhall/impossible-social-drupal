import { Command } from 'ckeditor5/src/core';

/**
 * Creates SimpleAds
 */
function createSimpleAds(writer, attributes) {
  return writer.createElement('SimpleAds', attributes);
}

/**
 * Command for inserting <simpleads> tag into ckeditor.
 */
export default class SimpleAdsCommand extends Command {
  execute(attributes) {
    const SimpleAdsEditing = this.editor.plugins.get('SimpleAdsEditing');
    const dataAttributeMapping = Object.entries(SimpleAdsEditing.attrs).reduce(
      (result, [key, value]) => {
        result[value] = key;
        return result;
      },
      {},
    );
    const modelAttributes = Object.keys(attributes).reduce(
      (result, attribute) => {
        if (dataAttributeMapping[attribute]) {
          result[dataAttributeMapping[attribute]] = attributes[attribute];
        }
        return result;
      },
      {},
    );

    this.editor.model.change((writer) => {
      this.editor.model.insertContent(
        createSimpleAds(writer, modelAttributes),
      );
    });
  }

  refresh() {
    const model = this.editor.model;
    const selection = model.document.selection;
    const allowedIn = model.schema.findAllowedParent(
      selection.getFirstPosition(),
      'SimpleAds',
    );
    this.isEnabled = allowedIn !== null;
  }
}
