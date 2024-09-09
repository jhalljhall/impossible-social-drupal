!function(e,t){"object"==typeof exports&&"object"==typeof module?module.exports=t():"function"==typeof define&&define.amd?define([],t):"object"==typeof exports?exports.CKEditor5=t():(e.CKEditor5=e.CKEditor5||{},e.CKEditor5.SimpleAds=t())}(self,(()=>(()=>{var e={"ckeditor5/src/core.js":(e,t,i)=>{e.exports=i("dll-reference CKEditor5.dll")("./src/core.js")},"ckeditor5/src/engine.js":(e,t,i)=>{e.exports=i("dll-reference CKEditor5.dll")("./src/engine.js")},"ckeditor5/src/ui.js":(e,t,i)=>{e.exports=i("dll-reference CKEditor5.dll")("./src/ui.js")},"ckeditor5/src/widget.js":(e,t,i)=>{e.exports=i("dll-reference CKEditor5.dll")("./src/widget.js")},"dll-reference CKEditor5.dll":e=>{"use strict";e.exports=CKEditor5.dll}},t={};function i(r){var s=t[r];if(void 0!==s)return s.exports;var o=t[r]={exports:{}};return e[r](o,o.exports,i),o.exports}i.d=(e,t)=>{for(var r in t)i.o(t,r)&&!i.o(e,r)&&Object.defineProperty(e,r,{enumerable:!0,get:t[r]})},i.o=(e,t)=>Object.prototype.hasOwnProperty.call(e,t);var r={};return(()=>{"use strict";i.d(r,{default:()=>m});var e=i("ckeditor5/src/core.js"),t=i("ckeditor5/src/widget.js");class s extends e.Command{execute(e){const t=this.editor.plugins.get("SimpleAdsEditing"),i=Object.entries(t.attrs).reduce(((e,[t,i])=>(e[i]=t,e)),{}),r=Object.keys(e).reduce(((t,r)=>(i[r]&&(t[i[r]]=e[r]),t)),{});this.editor.model.change((e=>{this.editor.model.insertContent(function(e,t){return e.createElement("SimpleAds",t)}(e,r))}))}refresh(){const e=this.editor.model,t=e.document.selection,i=e.schema.findAllowedParent(t.getFirstPosition(),"SimpleAds");this.isEnabled=null!==i}}class o extends e.Plugin{static get requires(){return[t.Widget]}init(){this.attrs={SimpleAdsGroup:"data-group",SimpleAdsRotationType:"data-rotation-type",SimpleAdsRandomLimit:"data-random-limit",SimpleAdsImpressions:"data-impressions"};const e=this.editor.config.get("SimpleAds");if(!e)return;const{previewURL:t,themeError:i}=e;this.previewUrl=t,this.themeError=i||`\n      <p>${Drupal.t("An error occurred while trying to preview the SimpleAds. Please save your work and reload this page.")}<p>\n    `,this._defineSchema(),this._defineConverters(),this.editor.commands.add("SimpleAds",new s(this.editor))}async _fetchPreview(e){const t={group:e.getAttribute("SimpleAdsGroup"),rotation:e.getAttribute("SimpleAdsRotationType"),multiple_random_limit:e.getAttribute("SimpleAdsRandomLimit"),rotation_impressions:e.getAttribute("SimpleAdsImpressions")},i=await fetch(`${this.previewUrl}?${new URLSearchParams(t)}`);return i.ok?await i.text():this.themeError}_defineSchema(){this.editor.model.schema.register("SimpleAds",{allowWhere:"$block",isObject:!0,isContent:!0,isBlock:!0,allowAttributes:Object.keys(this.attrs)}),this.editor.editing.view.domConverter.blockElements.push("simpleads")}_defineConverters(){const e=this.editor.conversion;e.for("upcast").elementToElement({model:"SimpleAds",view:{name:"simpleads"}}),e.for("dataDowncast").elementToElement({model:"SimpleAds",view:{name:"simpleads"}}),e.for("editingDowncast").elementToElement({model:"SimpleAds",view:(e,{writer:i})=>{const r=i.createContainerElement("figure");return(0,t.toWidget)(r,i,{label:Drupal.t("SimpleAds")})}}).add((e=>(e.on("attribute:SimpleAdsGroup:SimpleAds",((e,t,i)=>{const r=i.writer,s=t.item,o=i.mapper.toViewElement(t.item),n=r.createRawElement("div",{"data-simpleads-preview":"loading",class:"simpleads-preview"});r.insert(r.createPositionAt(o,0),n),this._fetchPreview(s).then((e=>{n&&this.editor.editing.view.change((t=>{const i=t.createRawElement("div",{class:"simpleads-preview","data-simpleads-preview":"ready"},(t=>{t.innerHTML=e}));t.insert(t.createPositionBefore(n),i),t.remove(n)}))}))})),e))),Object.keys(this.attrs).forEach((t=>{const i={model:{key:t,name:"SimpleAds"},view:{name:"simpleads",key:this.attrs[t]}};e.for("dataDowncast").attributeToAttribute(i),e.for("upcast").attributeToAttribute(i)}))}static get pluginName(){return"SimpleAdsEditing"}}var n=i("ckeditor5/src/ui.js");var d=i("ckeditor5/src/engine.js");class l extends d.DomEventObserver{constructor(e){super(e),this.domEventType="dblclick"}onDomEvent(e){this.fire(e.type,e)}}class a extends e.Plugin{init(){const e=this.editor,t=this.editor.config.get("SimpleAds");if(!t)return;const{dialogURL:i,openDialog:r,dialogSettings:s={}}=t;if(!i||"function"!=typeof r)return;e.ui.componentFactory.add("SimpleAds",(t=>{const o=e.commands.get("SimpleAds"),d=new n.ButtonView(t);return d.set({label:Drupal.t("SimpleAds"),icon:'<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-badge-ad" viewBox="0 0 16 16">\n  <path d="m3.7 11 .47-1.542h2.004L6.644 11h1.261L5.901 5.001H4.513L2.5 11h1.2zm1.503-4.852.734 2.426H4.416l.734-2.426h.053zm4.759.128c-1.059 0-1.753.765-1.753 2.043v.695c0 1.279.685 2.043 1.74 2.043.677 0 1.222-.33 1.367-.804h.057V11h1.138V4.685h-1.16v2.36h-.053c-.18-.475-.68-.77-1.336-.77zm.387.923c.58 0 1.002.44 1.002 1.138v.602c0 .76-.396 1.2-.984 1.2-.598 0-.972-.449-.972-1.248v-.453c0-.795.37-1.24.954-1.24z"/>\n  <path d="M14 3a1 1 0 0 1 1 1v8a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h12zM2 2a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2H2z"/>\n</svg>',tooltip:!0}),d.bind("isOn","isEnabled").to(o,"value","isEnabled"),this.listenTo(d,"execute",(()=>{r(i,(({attributes:t})=>{e.execute("SimpleAds",t)}),s)})),d}));const o=e.editing.view,d=o.document;o.addObserver(l),e.listenTo(d,"dblclick",((t,o)=>{const n=e.editing.mapper.toModelElement(o.target);if(n&&void 0!==n.name&&"SimpleAds"===n.name){const t={group:n.getAttribute("SimpleAdsGroup"),rotation:n.getAttribute("SimpleAdsRotationType"),multiple_random_limit:n.getAttribute("SimpleAdsRandomLimit"),rotation_impressions:n.getAttribute("SimpleAdsImpressions")};r(`${i}?${new URLSearchParams(t)}`,(({attributes:t})=>{e.execute("SimpleAds",t)}),s)}}))}}class c extends e.Plugin{static get requires(){return[o,a]}static get pluginName(){return"SimpleAds"}}const m={SimpleAds:c}})(),r=r.default})()));