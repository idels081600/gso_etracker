(() => {
  const data = window.ibPageData;
  const records = new Map(data.records.map(record => [Number(record.id), record]));
  const itemByLabel = new Map();
  const peso = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });
  const editorModal = new bootstrap.Modal(document.getElementById('ibEditorModal'));
  const activeItemsModal = new bootstrap.Modal(document.getElementById('activeItemsModal'));
  const deliveryModal = new bootstrap.Modal(document.getElementById('deliveryModal'));
  const quantityEditorModal = new bootstrap.Modal(document.getElementById('quantityEditorModal'));
  const groupContainer = document.getElementById('officeGroups');
  const activeItemRows = document.getElementById('activeItemRows');
  const editorForm = document.getElementById('ibEditorForm');
  const activeItemsForm = document.getElementById('activeItemsForm');
  const deliveryForm = document.getElementById('deliveryForm');
  let deliveryToken = '';
  let editorGroupSequence = 0;
  let nextAutomaticItemNo = Number(data.next_item_no) || 1;
  data.items.forEach(item => itemByLabel.set(`${item.item_name} — ${item.item_no}`, item));

  const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[char]));
  const allowedUnits = ['UNIT','PCS','BOX','BOT','GAL','PAD','PACKS','ROLLS','SHEETS','REAMS'];
  const unitAliases = { PIECE:'PCS',PIECES:'PCS',PCS:'PCS',BOX:'BOX',BOXES:'BOX',BOTTLE:'BOT',BOTTLES:'BOT',BOT:'BOT',GALLON:'GAL',GALLONS:'GAL',GAL:'GAL',PAD:'PAD',PADS:'PAD',PACK:'PACKS',PACKS:'PACKS',ROLL:'ROLLS',ROLLS:'ROLLS',SHEET:'SHEETS',SHEETS:'SHEETS',REAM:'REAMS',REAMS:'REAMS',UNIT:'UNIT',UNITS:'UNIT' };
  function unitOptions(selected='') {
    const original=String(selected||'').trim();
    const normalized=unitAliases[original.toUpperCase()]||original.toUpperCase();
    const options=['<option value="">Select unit…</option>',...allowedUnits.map(unit=>`<option value="${unit}" ${normalized===unit?'selected':''}>${unit}</option>`)];
    if(original && !allowedUnits.includes(normalized)) options.push(`<option value="${escapeHtml(original)}" selected>${escapeHtml(original)} (Existing)</option>`);
    return options.join('');
  }
  const uuid = () => crypto.randomUUID ? crypto.randomUUID() : 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, c => { const r=Math.random()*16|0,v=c==='x'?r:(r&3|8); return v.toString(16); });
  async function send(payload) {
    const response = await fetch('Logi_ib_action.php', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({...payload, csrf_token:data.csrf}) });
    const result = await response.json().catch(() => ({success:false,message:'The server returned an invalid response.'}));
    if (!response.ok || !result.success) throw new Error(result.message || 'The action failed.');
    return result;
  }
  function busy(button, active, label='Working…') {
    if (!button) return; if (active) { button.dataset.label=button.innerHTML; button.disabled=true; button.innerHTML=`<span class="spinner-border spinner-border-sm"></span> ${label}`; } else { button.disabled=false; button.innerHTML=button.dataset.label || button.innerHTML; }
  }
  function officeOptions(selected='') { return `<option value="">Select office…</option>${data.offices.map(o=>`<option value="${o.id}" ${Number(selected)===o.id?'selected':''}>${escapeHtml(o.office_name)}</option>`).join('')}`; }
  function itemListId() { return 'ibItemCatalog'; }
  function ensureDatalist() { if (document.getElementById(itemListId())) return; const list=document.createElement('datalist'); list.id=itemListId(); list.innerHTML=data.items.map(i=>`<option value="${escapeHtml(i.item_name)} — ${escapeHtml(i.item_no)}"></option>`).join(''); document.body.appendChild(list); }
  function updateManualHelp(row, assignAutomaticNumber=false) {
    const checked=row.querySelector('.manual-add-toggle').checked;
    const numberInput=row.querySelector('.manual-item-no');
    row.classList.toggle('will-add-inventory',checked);
    if(checked) {
      if(assignAutomaticNumber || !numberInput.value) { numberInput.value=String(nextAutomaticItemNo++); numberInput.dataset.automatic='1'; }
      numberInput.readOnly=true;
      numberInput.required=false;
      numberInput.placeholder='Assigned automatically';
      row.querySelector('.manual-help').textContent=`Stock number ${numberInput.value} is assigned automatically from the latest inventory number. The server confirms it when you save.`;
    } else {
      if(numberInput.dataset.automatic==='1') { numberInput.value=''; delete numberInput.dataset.automatic; }
      numberInput.readOnly=false;
      numberInput.required=false;
      numberInput.placeholder='Stock number (optional)';
      row.querySelector('.manual-help').textContent='Monitoring only. A stock number is optional, and deliveries will not add this item to Inventory or change stock.';
    }
  }
  function setRowMode(row, manual) {
    row.querySelector('.catalog-fields').hidden = manual;
    row.querySelector('.manual-fields').hidden = !manual;
    row.querySelector('.manual-add-option').hidden = !manual;
    row.querySelector('.manual-help').hidden = !manual;
    row.querySelector('.item-search').required = !manual;
    row.querySelector('.manual-item-name').required = manual;
    row.querySelector('.manual-item-unit').required = manual;
    if(!manual) row.querySelector('.manual-add-toggle').checked=false;
    updateManualHelp(row);
  }
  function addItemRow(target, itemData=null) {
    const row=document.createElement('div'); row.className='editor-item-row';
    const manual=Boolean(itemData && (itemData.is_new_item || !Number(itemData.item_id)));
    const manualAdd=Boolean(manual && Number(itemData?.add_to_inventory)===1);
    const found=!manual && itemData ? data.items.find(i=>i.id===Number(itemData.item_id)) : null;
    const label=found ? `${found.item_name} — ${found.item_no}` : '';
    row.innerHTML=`<div class="item-choice"><label>Inventory item</label><div class="form-check form-switch manual-item-switch"><input class="form-check-input manual-toggle" type="checkbox" ${manual?'checked':''}><label class="form-check-label">Not in inventory yet</label></div><div class="catalog-fields"><input class="form-control item-search" list="${itemListId()}" value="${escapeHtml(label)}" placeholder="Search item name or stock number"><input type="hidden" class="item-id" value="${found?.id||''}"><small class="item-hint">${found?`${escapeHtml(found.unit||'No unit')} · Current stock ${found.current_balance}`:'Select from the item list'}</small></div><div class="manual-fields" ${manual?'':'hidden'}><input class="form-control manual-item-no" maxlength="500" value="${escapeHtml(manual?itemData?.item_no||'':'')}" placeholder="Stock number (optional)"><input class="form-control manual-item-name" maxlength="500" value="${escapeHtml(manual?itemData?.item_name||'':'')}" placeholder="New item name"><select class="form-select manual-item-unit">${unitOptions(manual?itemData?.unit||'':'')}</select></div><label class="manual-add-option" ${manual?'':'hidden'}><input class="form-check-input manual-add-toggle" type="checkbox" ${manualAdd?'checked':''}><span class="manual-add-copy"><strong>Add this item to Inventory on first delivery</strong><span>Assign the next stock number and create the inventory record when the first delivery is posted.</span></span></label><small class="manual-help" ${manual?'':'hidden'}></small></div><div><label>Quantity</label><input class="form-control item-qty" type="number" min="1" step="1" value="${itemData?.planned_quantity||''}" required></div><div class="price-field"><label>Unit price</label><div class="input-group"><span class="input-group-text">₱</span><input class="form-control item-price" type="number" min="0" step="0.01" value="${itemData?.unit_price||''}" required></div></div><div><label>Line amount</label><div class="editor-line-total">₱0.00</div></div><button type="button" class="btn btn-outline-danger remove-item" title="Remove item"><i class="fas fa-times"></i></button>`;
    target.appendChild(row); setRowMode(row,manual); target===activeItemRows?calculateActiveAdd():calculateEditor();
  }
  function addGroup(groupData=null, collapsed=false) {
    const section=document.createElement('section'); section.className='editor-office-group';
    const bodyId=`editorGroupBody${++editorGroupSequence}`;
    section.innerHTML=`<div class="editor-group-head"><div><div class="editor-group-label"><button class="editor-group-toggle${collapsed?' collapsed':''}" type="button" data-bs-toggle="collapse" data-bs-target="#${bodyId}" aria-expanded="${collapsed?'false':'true'}" aria-controls="${bodyId}" aria-label="Toggle office group items"><i class="fas fa-chevron-right" aria-hidden="true"></i></button><label>Office <span class="text-danger">*</span></label></div><select class="form-select group-office" required>${officeOptions(groupData?.office_id)}</select></div><div><label>Description / purpose <span class="text-danger">*</span></label><input class="form-control group-description" maxlength="500" value="${escapeHtml(groupData?.description||'')}" placeholder="What this office allocation covers" required></div><button class="btn btn-outline-danger remove-group" type="button"><i class="fas fa-trash"></i> Remove group</button></div><div class="collapse${collapsed?'':' show'} editor-group-body" id="${bodyId}"><div class="editor-group-items"></div><div class="px-2 pb-2"><button class="btn btn-sm btn-outline-success add-item" type="button"><i class="fas fa-plus"></i> Add item</button></div></div>`;
    groupContainer.appendChild(section); const items=section.querySelector('.editor-group-items');
    (groupData?.items?.length ? groupData.items : [null]).forEach(item=>addItemRow(items,item));
  }
  function calculateEditor() {
    let total=0; groupContainer.querySelectorAll('.editor-item-row').forEach(row=>{ const qty=Number(row.querySelector('.item-qty').value)||0, price=Number(row.querySelector('.item-price').value)||0, amount=qty*price; row.querySelector('.editor-line-total').textContent=peso.format(amount); total+=amount; });
    document.getElementById('editorGrandTotal').textContent=peso.format(total);
  }
  function calculateActiveAdd() {
    let total=0; activeItemRows.querySelectorAll('.editor-item-row').forEach(row=>{const qty=Number(row.querySelector('.item-qty').value)||0,price=Number(row.querySelector('.item-price').value)||0,amount=qty*price;row.querySelector('.editor-line-total').textContent=peso.format(amount);total+=amount;});
    document.getElementById('activeAddTotal').textContent=peso.format(total);
  }
  function readItemRows(container) {
    return [...container.querySelectorAll('.editor-item-row')].map(row=>{const manual=row.querySelector('.manual-toggle').checked;return {is_new_item:manual,add_to_inventory:manual&&row.querySelector('.manual-add-toggle').checked,item_id:manual?null:Number(row.querySelector('.item-id').value),item_no:manual?row.querySelector('.manual-item-no').value.trim():'',item_name:manual?row.querySelector('.manual-item-name').value.trim():'',unit:manual?row.querySelector('.manual-item-unit').value.trim():'',quantity:Number(row.querySelector('.item-qty').value),unit_price:row.querySelector('.item-price').value};});
  }
  function itemsAreInvalid(items) { return items.some(item=>item.quantity<1||item.unit_price===''||(item.is_new_item?(!item.item_name||!item.unit):!item.item_id)); }
  function openEditor(record=null) {
    document.getElementById('ibEditorTitle').textContent=record?'Edit Draft IB':'Create IB'; document.getElementById('editIbId').value=record?.id||''; document.getElementById('ibNumber').value=record?.ib_no||''; groupContainer.innerHTML='';
    (record?.groups?.length ? record.groups : [null]).forEach(group=>addGroup(group,Boolean(record))); calculateEditor(); editorModal.show();
  }
  ensureDatalist();
  document.getElementById('createIbBtn').addEventListener('click',()=>openEditor());
  document.getElementById('addOfficeGroup').addEventListener('click',()=>addGroup());
  groupContainer.addEventListener('click',event=>{ const removeItem=event.target.closest('.remove-item'), removeGroup=event.target.closest('.remove-group'), addItem=event.target.closest('.add-item'); if(removeItem){ const group=removeItem.closest('.editor-office-group'); if(group.querySelectorAll('.editor-item-row').length===1)return alert('Each office group needs at least one item.'); removeItem.closest('.editor-item-row').remove(); calculateEditor(); } if(removeGroup){ if(groupContainer.children.length===1)return alert('An IB needs at least one office group.'); removeGroup.closest('.editor-office-group').remove(); calculateEditor(); } if(addItem)addItemRow(addItem.closest('.editor-office-group').querySelector('.editor-group-items')); });
  groupContainer.addEventListener('input',event=>{
    if(event.target.matches('.item-qty,.item-price')) calculateEditor();
    if(event.target.matches('.item-search')) { const item=itemByLabel.get(event.target.value), row=event.target.closest('.editor-item-row'); row.querySelector('.item-id').value=item?.id||''; row.querySelector('.item-hint').textContent=item?`${item.unit||'No unit'} · Current stock ${item.current_balance}`:'Select an exact item from the list'; }
  });
  groupContainer.addEventListener('change',event=>{
    const row=event.target.closest('.editor-item-row');
    if(event.target.matches('.manual-add-toggle')) { updateManualHelp(row,event.target.checked); return; }
    if(!event.target.matches('.manual-toggle')) return;
    const manual=event.target.checked; setRowMode(row,manual);
    if(manual){ row.querySelector('.item-id').value=''; row.querySelector('.item-search').value=''; }
    else { row.querySelectorAll('.manual-fields input').forEach(input=>input.value=''); }
  });
  editorForm.addEventListener('submit',async event=>{
    event.preventDefault(); const button=document.getElementById('saveDraftBtn');
    const groups=[...document.querySelectorAll('.editor-office-group')].map(group=>({
      office_id:Number(group.querySelector('.group-office').value), description:group.querySelector('.group-description').value.trim(),
      items:readItemRows(group)
    }));
    const invalid=groups.some(group=>!group.office_id||!group.description||itemsAreInvalid(group.items));
    if(invalid) return alert('Complete every office, description, item, quantity, unit, and unit price.');
    const ibId=Number(document.getElementById('editIbId').value)||0; busy(button,true,'Saving…');
    try{const result=await send({action:ibId?'update_draft':'create',ib_id:ibId,ib_no:document.getElementById('ibNumber').value.trim(),groups});alert(result.message);location.reload();}catch(error){alert(error.message);busy(button,false);}
  });
  document.querySelectorAll('.edit-ib').forEach(button=>button.addEventListener('click',()=>openEditor(records.get(Number(button.dataset.id)))));
  function openActiveItems(record) {
    document.getElementById('activeAddIbId').value=record.id; document.getElementById('activeAddIbNo').textContent=record.ib_no;
    document.getElementById('activeAddGroup').innerHTML=record.groups.map(group=>`<option value="${group.id}">${escapeHtml(group.office_name)} — ${escapeHtml(group.description)}</option>`).join('');
    activeItemRows.innerHTML=''; addItemRow(activeItemRows); calculateActiveAdd(); activeItemsModal.show();
  }
  document.querySelectorAll('.add-active-items').forEach(button=>button.addEventListener('click',()=>openActiveItems(records.get(Number(button.dataset.id)))));
  document.getElementById('activeAddRow').addEventListener('click',()=>addItemRow(activeItemRows));
  activeItemRows.addEventListener('click',event=>{const remove=event.target.closest('.remove-item');if(!remove)return;if(activeItemRows.querySelectorAll('.editor-item-row').length===1)return alert('Add at least one item.');remove.closest('.editor-item-row').remove();calculateActiveAdd();});
  activeItemRows.addEventListener('input',event=>{if(event.target.matches('.item-qty,.item-price'))calculateActiveAdd();if(event.target.matches('.item-search')){const item=itemByLabel.get(event.target.value),row=event.target.closest('.editor-item-row');row.querySelector('.item-id').value=item?.id||'';row.querySelector('.item-hint').textContent=item?`${item.unit||'No unit'} · Current stock ${item.current_balance}`:'Select an exact item from the list';}});
  activeItemRows.addEventListener('change',event=>{const row=event.target.closest('.editor-item-row');if(event.target.matches('.manual-add-toggle')){updateManualHelp(row,event.target.checked);return;}if(!event.target.matches('.manual-toggle'))return;const manual=event.target.checked;setRowMode(row,manual);if(manual){row.querySelector('.item-id').value='';row.querySelector('.item-search').value='';}else row.querySelectorAll('.manual-fields input').forEach(input=>input.value='');});
  activeItemsForm.addEventListener('submit',async event=>{event.preventDefault();const button=document.getElementById('saveActiveItemsBtn'),items=readItemRows(activeItemRows),groupId=Number(document.getElementById('activeAddGroup').value),ibId=Number(document.getElementById('activeAddIbId').value);if(!groupId||!items.length||itemsAreInvalid(items))return alert('Select an office group and complete every item, quantity, unit, and unit price.');busy(button,true,'Adding…');try{const result=await send({action:'add_active_items',ib_id:ibId,group_id:groupId,items});alert(result.message);location.reload();}catch(error){alert(error.message);busy(button,false);}});
  async function simpleAction(button,payload,confirmation,label){ if(!confirm(confirmation))return; busy(button,true,label); try{const result=await send(payload);alert(result.message);location.reload();}catch(error){alert(error.message);busy(button,false);} }
  document.querySelectorAll('.activate-ib').forEach(button=>button.addEventListener('click',()=>simpleAction(button,{action:'activate',ib_id:Number(button.dataset.id)},'Activate this IB? Its offices, items, quantities, and prices will be frozen.','Activating…')));
  document.querySelectorAll('.cancel-ib').forEach(button=>button.addEventListener('click',()=>{const reason=prompt('Enter the cancellation reason:');if(!reason)return;simpleAction(button,{action:'cancel',ib_id:Number(button.dataset.id),reason},'Cancel this IB?','Cancelling…');}));
  function openDelivery(record,onlyLineId=null){ document.getElementById('deliveryIbId').value=record.id; document.getElementById('deliveryIbNo').textContent=record.ib_no; document.getElementById('deliveryNotes').value=''; deliveryToken=uuid(); const container=document.getElementById('deliveryLines'); container.innerHTML=''; record.groups.forEach(group=>{const remaining=group.items.filter(line=>Number(line.planned_quantity)>Number(line.delivered_quantity)&&(!onlyLineId||Number(line.id)===Number(onlyLineId)));if(!remaining.length)return;const section=document.createElement('section');section.className='delivery-office';const normalizedOffice=String(group.office_name||'').trim().toUpperCase().replace(/[^A-Z0-9]+/g,'');const stocksInventory=normalizedOffice==='ADMIN'||normalizedOffice==='ADMINGSO';section.innerHTML=`<header><strong>${escapeHtml(group.office_name)}</strong><small>${escapeHtml(group.description)}</small><span class="delivery-stock-effect ${stocksInventory?'stocks-inventory':'monitoring-only'}">${stocksInventory?'Adds to inventory':'Monitoring only — inventory unchanged'}</span></header><div>${remaining.map(line=>{const left=Math.max(0,Number(line.planned_quantity)-Number(line.delivered_quantity));return `<div class="delivery-line" data-line-id="${line.id}" data-price="${line.unit_price}"><div class="item-meta"><strong>${escapeHtml(line.item_name)}</strong><small>${escapeHtml(line.item_no)} · ${escapeHtml(line.unit||'No unit')}</small></div><div class="remain"><small>Planned remaining</small><strong>${left}</strong></div><div><label class="form-label small mb-1">Delivered now</label><input class="form-control delivery-qty" type="number" min="0" step="1" value="0"></div><div class="value">${peso.format(0)}</div></div>`}).join('')}</div>`;container.appendChild(section);});calculateDelivery();deliveryModal.show(); }
  function calculateDelivery(){let total=0;document.querySelectorAll('.delivery-line').forEach(line=>{const value=(Number(line.querySelector('.delivery-qty').value)||0)*Number(line.dataset.price);line.querySelector('.value').textContent=peso.format(value);total+=value;});document.getElementById('deliveryTotal').textContent=peso.format(total);}
  document.querySelectorAll('.deliver-ib').forEach(button=>button.addEventListener('click',()=>openDelivery(records.get(Number(button.dataset.id)))));
  document.querySelectorAll('.quick-delivery:not(:disabled)').forEach(button=>button.addEventListener('click',()=>openDelivery(records.get(Number(button.dataset.ibId)),Number(button.dataset.lineId))));
  document.querySelectorAll('.edit-planned-quantity').forEach(button=>button.addEventListener('click',()=>{
    document.getElementById('quantityEditorIbId').value=button.dataset.ibId;
    document.getElementById('quantityEditorLineId').value=button.dataset.lineId;
    document.getElementById('quantityEditorContext').textContent=`${button.dataset.officeName} — ${button.dataset.itemName}`;
    document.getElementById('quantityEditorCurrent').textContent=Number(button.dataset.plannedQuantity).toLocaleString();
    document.getElementById('quantityEditorDelivered').textContent=Number(button.dataset.deliveredQuantity).toLocaleString();
    document.getElementById('quantityEditorValue').value=button.dataset.plannedQuantity;
    document.getElementById('quantityEditorReason').value='';
    quantityEditorModal.show();
    document.getElementById('quantityEditorModal').addEventListener('shown.bs.modal',()=>document.getElementById('quantityEditorValue').select(),{once:true});
  }));
  document.getElementById('quantityEditorForm').addEventListener('submit',async event=>{
    event.preventDefault();
    const button=document.getElementById('savePlannedQuantity');
    const plannedQuantity=Number(document.getElementById('quantityEditorValue').value);
    const reason=document.getElementById('quantityEditorReason').value.trim();
    if(!Number.isInteger(plannedQuantity)||plannedQuantity<1)return alert('Enter a positive whole-number planned quantity.');
    if(!reason)return alert('Enter the reason for changing the planned quantity.');
    busy(button,true,'Saving…');
    try{
      const result=await send({action:'update_item_quantity',ib_id:Number(document.getElementById('quantityEditorIbId').value),line_id:Number(document.getElementById('quantityEditorLineId').value),planned_quantity:plannedQuantity,reason});
      alert(result.message);
      location.reload();
    }catch(error){alert(error.message);busy(button,false);}
  });
  document.getElementById('deliveryLines').addEventListener('input',event=>{if(event.target.matches('.delivery-qty'))calculateDelivery();});
  deliveryForm.addEventListener('submit',async event=>{event.preventDefault();const button=event.submitter;const lines=[...document.querySelectorAll('.delivery-line')].map(line=>({line_id:Number(line.dataset.lineId),quantity:Number(line.querySelector('.delivery-qty').value)})).filter(line=>line.quantity>0);if(!lines.length)return alert('Enter at least one received quantity.');busy(button,true,'Posting…');try{const result=await send({action:'record_delivery',ib_id:Number(document.getElementById('deliveryIbId').value),delivery_date:document.getElementById('deliveryDate').value,notes:document.getElementById('deliveryNotes').value.trim(),idempotency_token:deliveryToken,lines});alert(result.message);location.reload();}catch(error){alert(error.message);busy(button,false);}});
  document.querySelectorAll('.reverse-delivery').forEach(button=>button.addEventListener('click',()=>{const reason=prompt('Enter the reason for reversing this entire delivery:');if(!reason)return;simpleAction(button,{action:'reverse_delivery',delivery_id:Number(button.dataset.deliveryId),reason},'Reverse this delivery and deduct its quantities from inventory?','Reversing…');}));
})();
