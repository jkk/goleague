create table players (
    pid int not null auto_increment primary key,
    status enum('active', 'inactive') not null default 'active',
    name varchar(255)
);
create table bands (
    bid int not null auto_increment primary key,
    name varchar(255),
    description varchar(255)
);
create table players_to_bands(
    pid int not null,
    bid int not null,
    primary key(pid, bid)
);
create table rounds (
    rid int not null auto_increment primary key,
    bid int not null,
    begins date,
    ends date
);
create table players_to_rounds (
    pid int not null,
    rid int not null,
    primary key(pid, rid)
);
create table results (
    pw int not null,
    pb int not null,
    rid int not null,
    result char(2),
    primary key(pw, pb, round)
);