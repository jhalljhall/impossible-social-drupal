import { Plugin } from 'ckeditor5/src/core';
import SimpleAdsEditing from './simpleadsediting';
import SimpleAdsUI from './simpleadsui';

/**
 * Main entry point to the SimpleAds.
 */
export default class SimpleAds extends Plugin {

  /**
   * @inheritdoc
   */
  static get requires() {
    return [SimpleAdsEditing, SimpleAdsUI];
  }

  /**
   * @inheritdoc
   */
  static get pluginName() {
    return 'SimpleAds';
  }
}
