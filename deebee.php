<?php

$instances = [] ;
class  Builder{
  static function _table($name,$fields,$keys){
    return [
      'name'=>$name
      ,'fields'=>fields.map(
        field=>{
          return $this->_field(...field)
        }
      )
      ,'keys'=>$this->_keys(...$keys)
      ];
  }
  static function _field($name,$type,$attrs){
    return {
      $name,$type,$attrs
    }
  }
  static _keys($primary,$foreign){
    return {
      primary,
      foreign:$this->_foreignKeys(foreign)
    };
  }
  static _foreignKeys(keys){
    return keys.map(
      key=>{
        return $this->_foreignKey(key)
      }
    );
  }
  static _foreignKey([name,[field,update,remove]]){
    return {
      name,
      references:{
        field,update,remove
      }
    };
  }
}

class DeeBee{

  static function getPGClass(){
    return PGDeeBee ;
  }

  static Builder = Builder


  function filterIt(str){
    if(str){

      str = str.split("").map(
        char=>{
          return char == "'" ? "\\'" : char == "`" ? "\\`" : char == '"' ? '\\"' : char
        }
      ).join("")
    
    }
    return str
  }

  function builder(){
    return DeeBee->Builder
  }

  function _____registerAction(actionname,callback){
    this[actionname] = callback
  }

  function _tbs(cb){
    $this->_db().query(
      `SHOW TABLES`,cb
    )
  }
  function _tb_exists(name,cb){
    let exists = null
    $this->_tbs(
      (e,tbs)=>{
        
        if(e) cb(exists)
        else  tbs.length ? tbs.forEach(
          (table,idx)=>{
            if(table[`Tables_in_${$this->dbname}`]==name) exists = table[`Tables_in_${$this->dbname}`]
            if(idx+1==tbs.length)cb(exists)
          }
        ) : cb(exists)
      }
    )
  }

  function _setUsersTable(tbl = null){
    $this->usersTable = tbl
  }
  function _setUsersPasswField(field='password'){
    $this->usersPasswField = field
  }
  function _getUsersPasswField(){
    return $this->usersPasswField
  }
  function _getUsersTable(tbl){
    return $this->usersTable
  }
  function _setUsersLogField(field='name'){
    $this->usersLogField = field
  }
  function _getUsersLogField(){
    return $this->usersLogField
  }
  function _getAdminsLogField(){
    return $this->adminsLogField
  }
  function _setAdminsTable(tbl = null){
    $this->adminsTable = tbl
  }
  function _setAdminsPasswField(field='password'){
    $this->AdminsPasswField = field
  }
  function _getAdminsPasswField(){
    return $this->AdminsPasswField
  }
  function  _getAdminsTable(tbl){
    return $this->adminsTable
  }
  function _setAdminsLogField(field='name'){
    $this->adminsLogField = field
  }
  function _getAdminsLogField(){
    return $this->adminsLogField
  }
  function __newFieldStr({name,type,attrs}){
    let fieldstr = `${name} ${type}`
    if(attrs&&attrs.length)attrs.forEach(
      attrname=>{
        fieldstr = `${fieldstr} ${attrname}`
      }
    )
    return fieldstr
  }
  function   __newFieldsStr(fields){
    let fieldsStr = ``
    fields.forEach(
      field=>{
        fieldsStr = `${fieldsStr == `` ? fieldsStr : `${fieldsStr},`}${$this->__newFieldStr(field)}`
      }
    )
    return fieldsStr
  }
  function   __newKeyStr({name,attrs,references}){
    let keyStr = `${name}`
    if(attrs){
      attrs.forEach(
        attrname=>{
          keyStr = `${keyStr} ${attrs[attrname]}`
        }
      )
    }
    if(references){
      references = [references]
      references.forEach(
        ({field,update,remove})=>{
          keyStr = `${ keyStr} REFERENCES ${field} ${(()=>{
            return update ? `ON UPDATE ${update}` : ""
          })()} ${(()=>{
            return remove ? `ON DELETE ${remove}` : ""
          })()} `
        }
      )
    }
    return keyStr
  }
  function   __newKeysStr(keys){
    let keysStr = ``
    if(keys.hasOwnProperty('primary') && keys.primary){
      keysStr = `${keysStr} ${$this->__newKeyStr({name:`PRIMARY KEY (${keys.primary})`,attrs:[]})}`
    }
    if(keys.hasOwnProperty('foreign') && keys.foreign!=undefined){
      if(keys.foreign.length){
        keys.foreign.forEach(
          key=>{
            key.name = `,FOREIGN KEY ${key.name}`
            keysStr = `${keysStr} ${$this->__newKeyStr(key)}`
          }
        )
      }
    }
    return keysStr
  }
  function   __newTableReq({name,fields,keys}){
    return `CREATE TABLE IF NOT EXISTS ${name} (${$this->__newFieldsStr(fields)} , ${$this->__newKeysStr(keys)})`
  }
  function   __createTable(table,cb){
    if(table){
      let req = $this->__newTableReq(table)
      $this->_db().query(
        req,cb
      )
    }else{
      cb(`provided argument ${table} is incorrect`,null)
    }
  }
  function   __createDataBase(name,tables,cb){
    let req = `create DATABASE IF NOT EXISTS ${name}`
    $this->_db().query(
      req,(err,res)=>{
        if(err){
          cb(err,res)
        }else{
          if(res){
            let errs = []
            let ress = []
            if(tables.length){
              tables.forEach(
                (table,idx)=>{
                  $this->__createTable(table,(e,r)=>{
                    errs.push(e)
                    ress.push(r)
                    if(idx+1==tables.length){
                      cb(errs,ress,tables.length)
                    }
                  })
                }
              )
              return
            }else{
              cb([err],[res])
            }
          }else{
            cb(['erreur non comprise||non understood error'],[res])
          }
        }
      }
    )
  }
  function   __dropTable(table,cb){
    let req = `DROP TABLE ${table}`
    $this->_db().query(
      req,cb
    )
  }
  function _db(){
    return $this->db
  }
  function   __db(){
    try{
      var deebee = $this->dbcreds.database
      $this->db = mysql.createConnection($this->dbcreds)
      $this->db.on(
        'error',err=>{
          $this->handleConnectErr(err,deebee)    
        }
      )
      $this->db.connect(
        err=>{
          try{
            if(err){
              if(resetDbErrs.includes(err)){
                console.log('error encounteered, made a fix\nretrying connection to database')
              }else{
                $this->handleConnectErr(err,deebee)
              }
            }else{
              $this->db.query(
                `use ${deebee}`,(e,r)=>{
                  if(e){
                    $this->handleConnectErr(e,deebee)
                  }if(r){
                    console.log('connected to mysql database')
                    $this->dbcreds.database = deebee
                    $this->_db().config.database=deebee
                    $this->setupTables()
                  }
                }
              )
              console.log('connected to mysql server')
            }
          }catch(e){
            if(resetDbErrs.includes(e.code)) $this->_db()
            $this->handleConnectErr(e,deebee)
          }
        }
      )
      return $this->db;
    }catch(e){
      if(resetDbErrs.includes(e.code)) $this->_db()
      $this->handleConnectErr(e,deebee)
    }
  }
  function handleConnectErr(err,deebee){
    if(err.sqlMessage==`Unknown database '${deebee}'`){
      $this->dbcreds.database = deebee
      deebee = $this->dbname
      $this->dbcreds.database = ''
      $this->db = mysql.createConnection($this->dbcreds)
      $this->db.connect(
        err=>{
          if(err)$this->handleConnectErr(err)
          else{
            $this->dbcreds.database = deebee
            $this->__createDataBase(
              $this->dbcreds.database,[],((e,r,tablessize)=>{
                  console.log(e,r)
                  let goterr=0
                  if(e.length){
                    e.forEach(
                      err=>{
                        if(err){
                          console.log(err)
                          goterr++
                        }
                      }
                    )
                  }
                  if(goterr==0){
                    if(r && r.length){
                      if(tablessize && (tablessize == r.length)){
                        $this->__db()
                      }else{
                        $this->__db()
                      }
                    }else{
                      $this->__db()
                    }
                  }
                }
              )
            )
          }
        }
      )
      console.log(`database ${deebee} not found creating it`)
    }else{
      console.log(err.hasOwnProperty('sqlMessage')?err.sqlMessage:err)
      $this->__db()
    }
  }
  function   __reqArr(fields_,vals_,statement){
      for(let i = 0; i < (fields_.length) ; i++){
        statement.bindParam(":"+fields_[i],vals_[i]);
      }
      return statement;
  }
  function   __valsStr(vals_,bef='',aft=''){
    let vals="";
    for(let i = 0 ; i < (vals_.length) ;i++ ){
      vals+=(bef)+vals_[i]+(i+1 < (vals_.length) ? "," : "")+(aft);
    }

    return vals;
  }
  function   __fvalStr(fields_=[],vals_=[],sep=',',bef='',aft='',vbef=""){
    let vals = "";
    for(let i = 0 ; i < (vals_.length) ;i++ ){
      vals+=(bef)+fields_[i]+"="+vbef+vals_[i]+(i+1 < (vals_.length) ? " "+sep+" " : "")+(aft);
    }
    return vals;
  }
  function   __condsStr(fields_=[],vals_=[],bef=""){
    return (fields_.length) ? " WHERE "+$this->__fvalStr(fields_,bef?fields_:vals_,"AND","","",bef) : "";
  }
  function   __selectFrom(table_,fields_=[],conds=[[],[]]){
    return "SELECT "+$this->__valsStr(fields_)+" FROM "+ table_ +((conds.length) ? $this->__condsStr(conds[0],conds[1]) : "");
  }
  function   __delFrom(table_,conds=[[],[]]){
    return "DELETE  FROM "+ table_ + $this->__condsStr(conds[0],conds[1],":");
  }
  function   __updtWhere(table_,fields_,vals_,conds=[[],[]]){
    return "UPDATE "+ table_ +" SET "+ $this->__fvalStr(fields_,vals_,",") +$this->__condsStr(conds[0],conds[1]);
  }
  function   __insertINTO(table,fields_=[],vals_=[]){
    return "INSERT INTO "+table+" ("+$this->__valsStr(fields_)+") VALUES ("+$this->__valsStr(vals_)+")";
  }
  function _req(type_,table_,fields_=[],vals_=[],conds=[[],[]]){
    let req = "";
    switch (type_) {
      case 'select':
        req = $this->__selectFrom(table_,fields_,conds);
        break;
      case 'insert':
        req = $this->__insertINTO(table_,fields_,vals_);
        break;
      case 'delete':
        req = $this->__delFrom(table_,conds);
        break;
      case 'update':
        req = $this->__updtWhere(table_,fields_,vals_,conds);
        break;
      default:
        // code...
        break;
    }
    return req;
  }
  function _insertReq(table_,fields_,vals_,c){
    return $this->_req(
          'insert'
          ,table_
          ,fields_
          ,vals_
        )
  }
  function _updateReq(table_,fields_,vals_,conds_){
    return $this->_req(
          'update'
          ,table_
          ,fields_
          ,vals_
          ,conds_
        )
  }
  function _delReq(table_,conds_,cb){
    return $this->_req(
      'delete',
      table_,
      conds_
    )
  }
  function   ___updateMember(fields_,vals_,id=null){
    let table=$this->_getUsersTable();
    let conds=[
      ['id']
      ,[id]
    ];
    return $this->_updateReq(table,fields_,vals_,conds);
  }
  function   ___loginreq(table,user,pass){
    return $this->_req("select",table,["id",$this->_getUsersLogField()],[],[[$this->_getUsersLogField(),$this->_getUsersPasswField()],["'"+user+"'",`password('${pass}')`]]);
  }
  function   ___delMember(name,id=null){
    let table=$this->_getUsersTable();
    let conds=[
      [''+(id?'id':$this->_getUsersLogField())]
      ,[''+(id?id:name)]
    ];
    return $this->_delReq(table,conds);
  }
  function   ___newNotification({member_id,concerned_id,type_}){
    let fields = ['member_id','concerned_id','type'];
    let vals   = [`${member_id}`,`${concerned_id}`,`'${type_}'`];
    let table  = '_notifications';
    return $this->_insertReq(table,fields,vals);
  }
  function   ___login(user,pass,cb){
    $this->db.query(
      $this->___loginreq($this->_getUsersTable(),user,pass)
      ,cb
    )
  }
  function   ___all_members(cb){
    let req = $this->_req('select',$this->_getUsersTable(),['*']);
    $this->db.query(req,(err,res)=>{
        if(res&&res.length){
          let r = []
          res.forEach(
            match=>{
                match.passw = null
                r.push(match)
            }
          )
          res = r
        }
        cb(err?err:res)
      }
    )
  }
  function   ___search(name,cb){
    let req = $this->_req('select',$this->_getUsersTable(),['id','name','email','gender','birthday','star_sign','zodiac','planet'],null,[['name'],[`'${name}' OR name LIKE '%${name}%' OR email LIKE '%${name}%'`]])
    $this->db.query(req,(err,res)=>{
        if(res && res.length){
          res = res.map(match=>{
            if(match.name.match(name)) match.matchedBy = 'name'
            if(match.email.match(name)) match.matchedBy = 'email'
            return match
          })
        }
        cb(err,res)
      }
    )
  }
  function   ___member(id,cb){
    let req = $this->_req('select',$this->_getUsersTable(),['id','name','email','gender','birthday','star_sign','zodiac','planet'],null,[['id'],[id]]);
    $this->db.query(req,(err,res)=>{
        if(err)cb(err,null)
        else{
          cb(res)
        }
      }
    )
  }
  function   ___update(type_,data,id,cb){

    let fields = data[0]
    let vals   = data[1]
    let  flds = []
    let  vls  = []
    fields.forEach(
      (fld,i)=>{
        if(fld!=$this->_getUsersPasswField()){
          flds.push(fields[i])
          vls.push(vals[i])
        }else{
          if(vals[i]){
            flds.push(fields[i])
            vls.push(`password(${vals[i]})`)
          }
        }
      }
    )
    fields = flds
    vals   = vls
    if(type_=='member'){
      $this->db.query(
        $this->___updateMember(fields,vals,id),cb
      )
    }
  }
  function setupTables(){
    
    if($this->configtables.length){
      let errs = []
      let res  = []
      const final = ()=>{
        if(errs.length){
          console.log('some errors occured when setting up the database')
          console.log(errs.join("\n"))
        }else{
          console.log('DeeBee is Ready')
          $this->ready = 1
        }
      }
      let made = 0
      $this->configtables.forEach(
        (table,idx)=>{
          $this->_tb_exists(
            table.name,tbl=>{
              if(tbl){
                made++
                res.push(tbl)
                if(made==$this->configtables.length)final()
              }else{
                $this->__createTable(table,(e,r)=>{
                  made++
                  if(e)errs.push(e)
                  res.push(r)
                  if(made==$this->configtables.length)final()
                })
              }
            }
          )
        }
      )
    }else{
      console.log('DeeBee is Ready')
      $this->ready = 1
    }
  }
  function configureActions(...actions){
      actions.forEach(
          ({name,cb})=>{
              $this->_____registerAction(
                  name,cb
              )
          }
      )
  }
  function __construct(creds,tables=[],type='mysql',dontconnect=false){
    super()
    if(type=='pg'){
      return new PGDeeBee(creds,tables)
    }
    $this->configtables = tables
    $this->dbname = creds.database
    $this->db = null;
    $this->dbcreds = creds
    $this->_setUsersTable('_members')
    if(dontconnect) return this
    $this->__db()
  }

}


class PGDeeBee extends DeeBee{


  _tbs(cb){
    $this->_db().query(
      `select * from pg_catalog.pg_tables where schemaname = '${$this->_db().database}'`,(e,r)=>{
        e = e ? e.stack : e
        r = r ? r.rows : r
        cb(e,r)
      }
      // `select * from information_schema.tables`,cb
    )
  }
  function   __db(){
    try{
      var deebee = $this->dbcreds.database
      $this->db = new Pool($this->dbcreds)
      $this->db.on(
        'error',err=>{
          $this->handleConnectErr(err,deebee)    
        }
      )
      $this->db.connect(
        err=>{
          try{
            if(err){
              if(resetDbErrs.includes(err)){
                console.log('error encounteered, made a fix\nretrying connection to database')
              }else{
                $this->handleConnectErr(err,deebee)
              }
            }else{
              console.log('connected to pgsql database')
              $this->dbcreds.database = deebee
              $this->_db().database=deebee
              $this->setupTables()
              console.log('connected to pgsql server')
            }
          }catch(e){
            if(resetDbErrs.includes(e.code)) $this->_db()
            $this->handleConnectErr(e,deebee)
          }
        }
      )
      return $this->db;
    }catch(e){
      if(resetDbErrs.includes(e.code)) $this->_db()
      $this->handleConnectErr(e,deebee)
    }
  }
  handleConnectErr(err,deebee){
    if(err.toString().match('too many clients already')) return
    if(err.toString().match(`database "${deebee}" does not exist`)){
      $this->dbcreds.database = deebee
      deebee = $this->dbname
      $this->dbcreds.database = ''
      $this->db = new Pool($this->dbcreds)
      $this->db.connect(
        err=>{
          if(err)$this->handleConnectErr(err)
          else{
            $this->dbcreds.database = deebee
            $this->__createDataBase(
              $this->dbcreds.database,[],((e,r,tablessize)=>{
                  console.log(e,r)
                  let goterr=0
                  if(e.length){
                    e.forEach(
                      err=>{
                        if(err){
                          console.log(err)
                          goterr++
                        }
                      }
                    )
                  }
                  if(goterr==0){
                    if(r && r.length){
                      if(tablessize && (tablessize == r.length)){
                        $this->__db()
                      }else{
                        $this->__db()
                      }
                    }else{
                      $this->__db()
                    }
                  }
                }
              )
            )
          }
        }
      )
      console.log(`database ${deebee} not found creating it`)
    }else{
      console.log(err.hasOwnProperty('sqlMessage')?err.sqlMessage:err)
      $this->__db()
    }
  }

  filterIt(str){
    if(str){

      str = str.split("").map(
        char=>{
          return char == "'" ? "\\'" : char == "`" ? "\\`" : char == '"' ? '\\"' : char
        }
      ).join("")
    
    }
    return str
  }
  constructor(creds,tables=[]){
    super(creds,tables)
    $this->configtables = tables
    $this->dbname = creds.database
    $this->db = null;
    $this->dbcreds = creds
    $this->_setUsersTable('_members')
    $this->__db()
  }

}
function cleanInstances(){
  instances.map(
    instance=>{
      instance.disconnect()
    }
  )
}
process.on('exit',cleanInstances.bind(null, {exit:true}))



module.exports = DeeBee

?>