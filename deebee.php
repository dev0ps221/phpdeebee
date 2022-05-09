<?php
let instances = [] 
const {Pool} = require('pg')
let resetDbErrs = ['ECONNRESET','PROTOCOL_PACKETS_OUT_OF_ORDER']
const mysql = require('mysql');
const {Ear} = require('@tek-tech/ears')
// const PGDeeBee = require('./pgdeebee')

class DeeBee extends Ear{

  static getPGClass(){
    return PGDeeBee 
  }

  static Builder = class {
    static _table(name,fields,keys){
      return {
        name
        ,fields:fields.map(
          field=>{
            return this._field(...field)
          }
        )
        ,keys:this._keys(...keys)
      }
    }
    static _field(name,type,attrs){
      return {
        name,type,attrs
      }
    }
    static _keys(primary,foreign){
      return {
        primary,
        foreign:this._foreignKeys(foreign)
      }
    }
    static _foreignKeys(keys){
      return keys.map(
        key=>{
          return this._foreignKey(key)
        }
      )
    }
    static _foreignKey([name,[field,update,remove]]){
      return {
        name,
        references:{
          field,update,remove
        }
      }
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

  builder(){
    return DeeBee.Builder
  }

  _____registerAction(actionname,callback){
    this[actionname] = callback
  }

  _tbs(cb){
    this._db().query(
      `SHOW TABLES`,cb
    )
  }
  _tb_exists(name,cb){
    let exists = null
    this._tbs(
      (e,tbs)=>{
        
        if(e) cb(exists)
        else  tbs.length ? tbs.forEach(
          (table,idx)=>{
            if(table[`Tables_in_${this.dbname}`]==name) exists = table[`Tables_in_${this.dbname}`]
            if(idx+1==tbs.length)cb(exists)
          }
        ) : cb(exists)
      }
    )
  }

  _setUsersTable(tbl = null){
    this.usersTable = tbl
  }
  _setUsersPasswField(field='password'){
    this.usersPasswField = field
  }
  _getUsersPasswField(){
    return this.usersPasswField
  }
  _getUsersTable(tbl){
    return this.usersTable
  }
  _setUsersLogField(field='name'){
    this.usersLogField = field
  }
  _getUsersLogField(){
    return this.usersLogField
  }
  _getAdminsLogField(){
    return this.adminsLogField
  }
  _setAdminsTable(tbl = null){
    this.adminsTable = tbl
  }
  _setAdminsPasswField(field='password'){
    this.AdminsPasswField = field
  }
  _getAdminsPasswField(){
    return this.AdminsPasswField
  }
  _getAdminsTable(tbl){
    return this.adminsTable
  }
  _setAdminsLogField(field='name'){
    this.adminsLogField = field
  }
  _getAdminsLogField(){
    return this.adminsLogField
  }
  __newFieldStr({name,type,attrs}){
    let fieldstr = `${name} ${type}`
    if(attrs&&attrs.length)attrs.forEach(
      attrname=>{
        fieldstr = `${fieldstr} ${attrname}`
      }
    )
    return fieldstr
  }
  __newFieldsStr(fields){
    let fieldsStr = ``
    fields.forEach(
      field=>{
        fieldsStr = `${fieldsStr == `` ? fieldsStr : `${fieldsStr},`}${this.__newFieldStr(field)}`
      }
    )
    return fieldsStr
  }
  __newKeyStr({name,attrs,references}){
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
  __newKeysStr(keys){
    let keysStr = ``
    if(keys.hasOwnProperty('primary') && keys.primary){
      keysStr = `${keysStr} ${this.__newKeyStr({name:`PRIMARY KEY (${keys.primary})`,attrs:[]})}`
    }
    if(keys.hasOwnProperty('foreign') && keys.foreign!=undefined){
      if(keys.foreign.length){
        keys.foreign.forEach(
          key=>{
            key.name = `,FOREIGN KEY ${key.name}`
            keysStr = `${keysStr} ${this.__newKeyStr(key)}`
          }
        )
      }
    }
    return keysStr
  }
  __newTableReq({name,fields,keys}){
    return `CREATE TABLE IF NOT EXISTS ${name} (${this.__newFieldsStr(fields)} , ${this.__newKeysStr(keys)})`
  }
  __createTable(table,cb){
    if(table){
      let req = this.__newTableReq(table)
      this._db().query(
        req,cb
      )
    }else{
      cb(`provided argument ${table} is incorrect`,null)
    }
  }
  __createDataBase(name,tables,cb){
    let req = `create DATABASE IF NOT EXISTS ${name}`
    this._db().query(
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
                  this.__createTable(table,(e,r)=>{
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
  __dropTable(table,cb){
    let req = `DROP TABLE ${table}`
    this._db().query(
      req,cb
    )
  }
  _db(){
    return this.db
  }
  __db(){
    try{
      var deebee = this.dbcreds.database
      this.db = mysql.createConnection(this.dbcreds)
      this.db.on(
        'error',err=>{
          this.handleConnectErr(err,deebee)    
        }
      )
      this.db.connect(
        err=>{
          try{
            if(err){
              if(resetDbErrs.includes(err)){
                console.log('error encounteered, made a fix\nretrying connection to database')
              }else{
                this.handleConnectErr(err,deebee)
              }
            }else{
              this.db.query(
                `use ${deebee}`,(e,r)=>{
                  if(e){
                    this.handleConnectErr(e,deebee)
                  }if(r){
                    console.log('connected to mysql database')
                    this.dbcreds.database = deebee
                    this._db().config.database=deebee
                    this.setupTables()
                  }
                }
              )
              console.log('connected to mysql server')
            }
          }catch(e){
            if(resetDbErrs.includes(e.code)) this._db()
            this.handleConnectErr(e,deebee)
          }
        }
      )
      return this.db;
    }catch(e){
      if(resetDbErrs.includes(e.code)) this._db()
      this.handleConnectErr(e,deebee)
    }
  }
  handleConnectErr(err,deebee){
    if(err.sqlMessage==`Unknown database '${deebee}'`){
      this.dbcreds.database = deebee
      deebee = this.dbname
      this.dbcreds.database = ''
      this.db = mysql.createConnection(this.dbcreds)
      this.db.connect(
        err=>{
          if(err)this.handleConnectErr(err)
          else{
            this.dbcreds.database = deebee
            this.__createDataBase(
              this.dbcreds.database,[],((e,r,tablessize)=>{
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
                        this.__db()
                      }else{
                        this.__db()
                      }
                    }else{
                      this.__db()
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
      this.__db()
    }
  }
  __reqArr(fields_,vals_,statement){
      for(let i = 0; i < (fields_.length) ; i++){
        statement.bindParam(":"+fields_[i],vals_[i]);
      }
      return statement;
  }
  __valsStr(vals_,bef='',aft=''){
    let vals="";
    for(let i = 0 ; i < (vals_.length) ;i++ ){
      vals+=(bef)+vals_[i]+(i+1 < (vals_.length) ? "," : "")+(aft);
    }

    return vals;
  }
  __fvalStr(fields_=[],vals_=[],sep=',',bef='',aft='',vbef=""){
    let vals = "";
    for(let i = 0 ; i < (vals_.length) ;i++ ){
      vals+=(bef)+fields_[i]+"="+vbef+vals_[i]+(i+1 < (vals_.length) ? " "+sep+" " : "")+(aft);
    }
    return vals;
  }
  __condsStr(fields_=[],vals_=[],bef=""){
    return (fields_.length) ? " WHERE "+this.__fvalStr(fields_,bef?fields_:vals_,"AND","","",bef) : "";
  }
  __selectFrom(table_,fields_=[],conds=[[],[]]){
    return "SELECT "+this.__valsStr(fields_)+" FROM "+ table_ +((conds.length) ? this.__condsStr(conds[0],conds[1]) : "");
  }
  __delFrom(table_,conds=[[],[]]){
    return "DELETE  FROM "+ table_ + this.__condsStr(conds[0],conds[1],":");
  }
  __updtWhere(table_,fields_,vals_,conds=[[],[]]){
    return "UPDATE "+ table_ +" SET "+ this.__fvalStr(fields_,vals_,",") +this.__condsStr(conds[0],conds[1]);
  }
  __insertINTO(table,fields_=[],vals_=[]){
    return "INSERT INTO "+table+" ("+this.__valsStr(fields_)+") VALUES ("+this.__valsStr(vals_)+")";
  }
  _req(type_,table_,fields_=[],vals_=[],conds=[[],[]]){
    let req = "";
    switch (type_) {
      case 'select':
        req = this.__selectFrom(table_,fields_,conds);
        break;
      case 'insert':
        req = this.__insertINTO(table_,fields_,vals_);
        break;
      case 'delete':
        req = this.__delFrom(table_,conds);
        break;
      case 'update':
        req = this.__updtWhere(table_,fields_,vals_,conds);
        break;
      default:
        // code...
        break;
    }
    return req;
  }
  _insertReq(table_,fields_,vals_,c){
    return this._req(
          'insert'
          ,table_
          ,fields_
          ,vals_
        )
  }
  _updateReq(table_,fields_,vals_,conds_){
    return this._req(
          'update'
          ,table_
          ,fields_
          ,vals_
          ,conds_
        )
  }
  _delReq(table_,conds_,cb){
    return this._req(
      'delete',
      table_,
      conds_
    )
  }
  ___updateMember(fields_,vals_,id=null){
    let table=this._getUsersTable();
    let conds=[
      ['id']
      ,[id]
    ];
    return this._updateReq(table,fields_,vals_,conds);
  }
  ___loginreq(table,user,pass){
    return this._req("select",table,["id",this._getUsersLogField()],[],[[this._getUsersLogField(),this._getUsersPasswField()],["'"+user+"'",`password('${pass}')`]]);
  }
  ___delMember(name,id=null){
    let table=this._getUsersTable();
    let conds=[
      [''+(id?'id':this._getUsersLogField())]
      ,[''+(id?id:name)]
    ];
    return this._delReq(table,conds);
  }
  ___newNotification({member_id,concerned_id,type_}){
    let fields = ['member_id','concerned_id','type'];
    let vals   = [`${member_id}`,`${concerned_id}`,`'${type_}'`];
    let table  = '_notifications';
    return this._insertReq(table,fields,vals);
  }
  ___login(user,pass,cb){
    this.db.query(
      this.___loginreq(this._getUsersTable(),user,pass)
      ,cb
    )
  }
  ___all_members(cb){
    let req = this._req('select',this._getUsersTable(),['*']);
    this.db.query(req,(err,res)=>{
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
  ___search(name,cb){
    let req = this._req('select',this._getUsersTable(),['id','name','email','gender','birthday','star_sign','zodiac','planet'],null,[['name'],[`'${name}' OR name LIKE '%${name}%' OR email LIKE '%${name}%'`]])
    this.db.query(req,(err,res)=>{
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
  ___member(id,cb){
    let req = this._req('select',this._getUsersTable(),['id','name','email','gender','birthday','star_sign','zodiac','planet'],null,[['id'],[id]]);
    this.db.query(req,(err,res)=>{
        if(err)cb(err,null)
        else{
          cb(res)
        }
      }
    )
  }
  ___update(type_,data,id,cb){

    let fields = data[0]
    let vals   = data[1]
    let  flds = []
    let  vls  = []
    fields.forEach(
      (fld,i)=>{
        if(fld!=this._getUsersPasswField()){
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
      this.db.query(
        this.___updateMember(fields,vals,id),cb
      )
    }
  }
  setupTables(){
    
    if(this.configtables.length){
      let errs = []
      let res  = []
      const final = ()=>{
        if(errs.length){
          console.log('some errors occured when setting up the database')
          console.log(errs.join("\n"))
        }else{
          console.log('DeeBee is Ready')
          this.ready = 1
        }
      }
      let made = 0
      this.configtables.forEach(
        (table,idx)=>{
          this._tb_exists(
            table.name,tbl=>{
              if(tbl){
                made++
                res.push(tbl)
                if(made==this.configtables.length)final()
              }else{
                this.__createTable(table,(e,r)=>{
                  made++
                  if(e)errs.push(e)
                  res.push(r)
                  if(made==this.configtables.length)final()
                })
              }
            }
          )
        }
      )
    }else{
      console.log('DeeBee is Ready')
      this.ready = 1
    }
  }
  configureActions(...actions){
      actions.forEach(
          ({name,cb})=>{
              this._____registerAction(
                  name,cb
              )
          }
      )
  }
  constructor(creds,tables=[],type='mysql',dontconnect=false){
    super()
    if(type=='pg'){
      return new PGDeeBee(creds,tables)
    }
    this.configtables = tables
    this.dbname = creds.database
    this.db = null;
    this.dbcreds = creds
    this._setUsersTable('_members')
    if(dontconnect) return this
    this.__db()
  }

}


class PGDeeBee extends DeeBee{


  _tbs(cb){
    this._db().query(
      `select * from pg_catalog.pg_tables where schemaname = '${this._db().database}'`,(e,r)=>{
        e = e ? e.stack : e
        r = r ? r.rows : r
        cb(e,r)
      }
      // `select * from information_schema.tables`,cb
    )
  }
  __db(){
    try{
      var deebee = this.dbcreds.database
      this.db = new Pool(this.dbcreds)
      this.db.on(
        'error',err=>{
          this.handleConnectErr(err,deebee)    
        }
      )
      this.db.connect(
        err=>{
          try{
            if(err){
              if(resetDbErrs.includes(err)){
                console.log('error encounteered, made a fix\nretrying connection to database')
              }else{
                this.handleConnectErr(err,deebee)
              }
            }else{
              console.log('connected to pgsql database')
              this.dbcreds.database = deebee
              this._db().database=deebee
              this.setupTables()
              console.log('connected to pgsql server')
            }
          }catch(e){
            if(resetDbErrs.includes(e.code)) this._db()
            this.handleConnectErr(e,deebee)
          }
        }
      )
      return this.db;
    }catch(e){
      if(resetDbErrs.includes(e.code)) this._db()
      this.handleConnectErr(e,deebee)
    }
  }
  handleConnectErr(err,deebee){
    if(err.toString().match('too many clients already')) return
    if(err.toString().match(`database "${deebee}" does not exist`)){
      this.dbcreds.database = deebee
      deebee = this.dbname
      this.dbcreds.database = ''
      this.db = new Pool(this.dbcreds)
      this.db.connect(
        err=>{
          if(err)this.handleConnectErr(err)
          else{
            this.dbcreds.database = deebee
            this.__createDataBase(
              this.dbcreds.database,[],((e,r,tablessize)=>{
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
                        this.__db()
                      }else{
                        this.__db()
                      }
                    }else{
                      this.__db()
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
      this.__db()
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
    this.configtables = tables
    this.dbname = creds.database
    this.db = null;
    this.dbcreds = creds
    this._setUsersTable('_members')
    this.__db()
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