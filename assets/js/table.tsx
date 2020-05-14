import * as React from "react";
import * as ReactDOM from "react-dom";
import * as axios from "axios";

import Select from 'react-select';

import {Accordion, Button, Card} from 'react-bootstrap';

const characters: any = {
    c: 'Cloud',
    b: 'Barret',
    t: 'Tifa',
    a: 'Aerith',
    i: 'Inventory'
};

let materiaChoices: any = [];
let materiaMatch: any = [];
let loadoutId: any = null;
let completeIndentLevels: any = [];

class MateriaCell extends React.Component<{ data: any, materias: any }, any> {
    constructor(props: any) {
        super(props);

        let val = null;
        if (typeof this.props.data.materia !== 'undefined') {
            val = materiaMatch.filter((m: any) => m.value === this.props.data.materia.id)[0];
        }

        this.state = {
            editing: false,
            locked: false,
            errored: false,
            selected: val
        }
    }

    onClick = () => {
        if (!this.state.editing) {
            this.setState({
                editing: true
            });
        }
    }

    onChange = (selected: any) => {
        let oldSelected = this.state.selected;
        this.setState({
            selected: selected,
            locked: true,
            errored: false
        });

        axios.default.patch(
            `/api/loadouts/${loadoutId}/items/${this.props.data.id}`,
            {materia: selected.value},
        )
            .then(() => {
                this.setState({
                    errored: false,
                    locked: false,
                    editing: false
                })
            })
            .catch(() => {
                this.setState({
                    errored: true,
                    locked: false,
                    selected: oldSelected
                });
            });
    }

    render() {
        let content;

        if (this.state.editing) {
            const style = {
                option: (styles: any, {data, isDisabled, isFocused, isSelected}: any) => {
                    return {
                        ...styles,
                        backgroundColor: isSelected || isFocused
                            ? data.color
                            : null,
                        color: isSelected || isFocused
                            ? 'white'
                            : data.color
                    }
                }
            };

            content = <Select
                value={this.state.selected}
                onChange={this.onChange}
                options={materiaChoices}
                defaultMenuIsOpen={true}
                isDisabled={this.state.locked}
                isLoading={this.state.locked}
                autoFocus={true}
                styles={style}
            />;
        } else {
            if (this.state.selected !== null) {
                content = <span className="materia-text">
                    {this.state.selected.label}
                </span>
            }
        }

        return <td
            className={['materia', `materia-${this.state.selected?.color}`].join(' ')}
            onClick={() => {
                this.onClick()
            }}>
            <div className="row no-gutters materia-row">
                <div className="col-auto">{String.fromCharCode(65 + this.props.data.row)}{this.props.data.col + 1}&nbsp;</div>
                <div className="col">
                    {content}
                </div>
            </div>
        </td>
    }
}

class LayoutRow extends React.Component<{ data: any, materias: any }, any> {
    render() {
        let cols = [];

        for (const item of this.props.data) {
            cols.push(<MateriaCell key={item.col} data={item} materias={this.props.materias} />);
        }

        return <tr>
            {cols}
        </tr>;
    }
}

class CharacterLayout extends React.Component<{ character: string, data: any, materias: any }, any> {
    render() {
        let items: any = {};

        for (const i of this.props.data.items) {
            if (i.charName == this.props.character) {
                if (typeof items[i.row] === 'undefined') {
                    items[i.row] = [];
                }

                items[i.row].push(i);
            }
        }

        return <div>
            <h3>Layout: {characters[this.props.character]}</h3>
            <table className="table table-bordered">
                <tbody>
                    <LayoutRow data={items[0]} materias={this.props.materias} />
                    <LayoutRow data={items[1]} materias={this.props.materias} />
                </tbody>
            </table>
        </div>
    }
}

class Table extends React.Component<{ id: any }, { data: any, materias: any }> {
    constructor(props: any) {
        super(props);

        this.state = {
            data: null,
            materias: null
        };
    }

    private doFetch() {
        axios.default.get(`/api/loadouts/${this.props.id}`)
            .then((res: any) => {
                axios.default.get('/api/materia')
                    .then((res2: any) => {
                        materiaChoices = [];
                        materiaMatch = [];

                        for (const group of res2.data) {
                            let options = [];

                            for (const materia of group.materias) {
                                let o = {
                                    value: materia.id,
                                    label: materia.name,
                                    color: group.color
                                };

                                options.push(o);
                                materiaMatch.push(o);
                            }

                            materiaChoices.push({
                                label: group.name,
                                color: group.color,
                                options: options
                            });
                        }

                        this.setState({
                            data: res.data,
                            materias: res2.data
                        });
                    })
            })
    }

    componentDidMount() {
        this.doFetch();
        loadoutId = this.props.id;
    }

    render() {
        if (null === this.state.data) {
            return <div>Loading</div>;
        }

        let layouts = [];
        let i = 0;

        for (const char of this.state.data.partyOrder) {
            layouts.push(
                <CharacterLayout key={i++} character={char} data={this.state.data} materias={this.state.materias} />)
        }

        return <div>
            {layouts}
        </div>;
    }
}

class ChildLoadoutListRow extends React.Component<{ data: any, active: number, indent: number, onClick: any, lastInGroup?: boolean }, any> {
    render() {
        for (const k in completeIndentLevels) {
            if (completeIndentLevels.hasOwnProperty(k) && Number.parseInt(k) > this.props.indent) {
                completeIndentLevels.splice(k, 1);
            }
        }

        let i = 0;
        const children = this.props.data.children.map((e: any) => <ChildLoadoutListRow
            key={i++}
            data={e}
            active={this.props.active}
            indent={this.props.indent + 1}
            onClick={this.props.onClick}
            lastInGroup={i === this.props.data.children.length} />);

        let cc = ['clickable-row'];

        if (this.props.active === this.props.data.loadout.id) {
            cc.push('clickable-row-active');
        }

        // <span style={{paddingLeft: (this.props.indent * 20) + 'px', width: 0, display: 'inline-block'}}>&nbsp;</span>
        let indents = [];
        for (let i = 0; i < this.props.indent; i++) {
            if (typeof completeIndentLevels[i] !== 'undefined') {
                indents.push(<span className={"indent indent-last"}>&nbsp;</span>)
                continue;
            }

            if (i === this.props.indent - 1) {
                if (this.props.lastInGroup === true) {
                    completeIndentLevels[this.props.indent - 1] = true;
                    indents.push(<span className={"indent indent-last"}>&#x2514;</span>)
                } else {
                    indents.push(<span className={"indent indent-last"}>&#x251C;</span>)
                }
            } else {
                indents.push(<span className={"indent"}>&#x2502;</span>)
            }
        }

        return <div>
            <div onClick={() => {
                this.props.onClick(this.props.data.loadout)
            }} className={cc.join(' ')}>
                {indents}
                <span>
                    {this.props.data.loadout.name}
                </span>
            </div>
            {children}
        </div>;
    }
}

class ChildLoadoutList extends React.Component<{ baseId: number }, any> {
    constructor(props: any) {
        super(props);

        this.state = {
            data: null
        }
    }

    componentDidMount() {
        axios.default.get(`/api/loadouts/${this.props.baseId}/tree`)
            .then((res: any) => {
                if (res.data.root.id !== res.data.loadout.id) {
                    axios.default.get(`/api/loadouts/${res.data.root.id}/tree`)
                        .then((res: any) => {
                            this.setState({
                                data: res.data
                            });
                        });
                } else {
                    this.setState({
                        data: res.data
                    });
                }
            });
    }

    onSelect = (selected: any) => {
        window.location.href = `/view/${selected.id}`;
    }

    render() {
        if (this.state.data === null) {
            return <div>Loading</div>;
        }

        return <div id="child-loadout-list">
            <ChildLoadoutListRow
                data={this.state.data}
                active={this.props.baseId}
                indent={0}
                onClick={this.onSelect} />
        </div>
    }
}

class ChangeDisplaySolutionMoveSlots extends React.Component<{ move: any }, any> {
    render() {
        let from;
        if (this.props.move.from.charName !== 'i') {
            from = `${String.fromCharCode(65 + this.props.move.from.row)}${this.props.move.from.col + 1}`;
        }

        let to;
        if (this.props.move.to.charName !== 'i') {
            to = `${String.fromCharCode(65 + this.props.move.to.row)}${this.props.move.to.col + 1}`;
        }

        return <tr className="change-display-solution-move-slots">
            <td className="from name">
                {characters[this.props.move.from.charName]}
            </td>
            <td className="from slot">
                {from}
            </td>
            <td className="swap">&#x27fa;</td>
            <td className="to name">
                {characters[this.props.move.to.charName]}
            </td>
            <td className="to slot">
                {to}
            </td>
        </tr>;
    }
}

class ChangeDisplaySolutionMoveMateria extends React.Component<{ move: any }, any> {
    render() {
        return <tr className="change-display-solution-move-materia">
            <td className="from materia" style={{color: this.props.move.from.materia?.type?.color}} colSpan={2}>
                {this.props.move.from.materia?.name}
            </td>
            <td className="swap">&nbsp;</td>
            <td className="to materia" style={{color: this.props.move.to.materia?.type?.color}} colSpan={2}>
                {this.props.move.to.materia?.name}
            </td>
        </tr>;
    }
}

class ChangeDisplaySolutionMove extends React.Component<{ move: any }, any> {
    render() {
        return <React.Fragment>
            <ChangeDisplaySolutionMoveSlots move={this.props.move} />
            <ChangeDisplaySolutionMoveMateria move={this.props.move} />
        </React.Fragment>;
    }
}

class ChangeDisplaySolution extends React.Component<{ solution: any, favorite?: boolean }, any> {
    render() {
        let i = 0;
        let moves = this.props.solution.moves.map((m: any) => <ChangeDisplaySolutionMove key={i++} move={m} />);

        return <div className="change-display-solution">
            <table className="table">
                <tbody>
                    {moves}
                </tbody>
            </table>
        </div>
    }
}

class ChangeDisplay extends React.Component<{ loadoutId: number, favorite: string }, any> {
    constructor(props: any) {
        super(props);

        this.state = {
            data: null,
            errtext: false
        };
    }

    reload = () => {
        this.setState({
            data: null,
            errtext: false
        });

        axios.default.get(`/api/loadouts/${this.props.loadoutId}/diff`)
            .then((res: any) => {
                this.setState({
                    data: res.data
                });
            })
            .catch((reason: any) => {
                if (reason.response?.status === 400 && reason.response?.data?.error === 'no_parent') {
                    this.setState({
                        errtext: 'This is the parent, thus cannot have any changes. Click "Create changes from this loadout" to copy this loadout.'
                    })
                } else {
                    this.setState({
                        errtext: 'Something went wrong. Feel free to hit me up on Discord if you feel like it wasn\'t supposed to happen!'
                    })
                }
            });
    }

    componentDidMount() {
        this.reload();
    }

    render() {
        if (this.state.data === null) {
            if (this.state.errtext !== false) {
                return <div>{this.state.errtext}</div>;
            }

            return <div>Calculating changes...</div>;
        }

        let mb;
        if (this.state.data.allSolutions.length === 0) {
            mb = <div className="row mb-2">
                <div className="col">
                    No solution.
                </div>
                <div className="col-auto">
                    <Button onClick={() => {
                        this.reload()
                    }} size="sm">refresh</Button>
                </div>
            </div>;
        } else {
            let fav = null;
            if (this.props.favorite !== '') {
                for (const solution of this.state.data.allSolutions) {
                    if (solution.key === this.props.favorite) {
                        fav = solution;
                        break;
                    }
                }
            }

            mb = <div>
                {fav}
                <div className="row mb-2">
                    <div className="col">
                    Minimum: {this.state.data.minimumDistance} ({this.state.data.matchingDistance.length} ways)
                    </div>
                    <div className="col-auto">
                        <Button onClick={() => {this.reload()}} size="sm">refresh</Button>
                    </div>
                </div>
                <div>
                    <ChangeDisplaySolution solution={this.state.data.matchingDistance[0]} />
                </div>
            </div>;
        }

        return <div>
            {mb}
        </div>;
    }
}

class LoadoutSidebar extends React.Component<any, any> {
    render() {
        let loadoutId = Number.parseInt(document.getElementById('table').getAttribute('data-id'));
        let fav = document.getElementById('sidebar').getAttribute('data-fav')

        return <Accordion defaultActiveKey="1">
            <Card>
                <Card.Header>
                    <Accordion.Toggle as={Button} variant="link" eventKey="0">
                        Navigation
                    </Accordion.Toggle>
                </Card.Header>
                <Accordion.Collapse eventKey="0">
                    <Card.Body>
                        <ChildLoadoutList baseId={loadoutId} />
                    </Card.Body>
                </Accordion.Collapse>
            </Card>
            <Card>
                <Card.Header>
                    <Accordion.Toggle as={Button} variant="link" eventKey="1">
                        Materia changelist
                    </Accordion.Toggle>
                </Card.Header>
                <Accordion.Collapse eventKey="1">
                    <Card.Body>
                        <ChangeDisplay loadoutId={loadoutId} favorite={fav} />
                    </Card.Body>
                </Accordion.Collapse>
            </Card>
        </Accordion>;
    }
}

ReactDOM.render(
    <Table id={document.getElementById('table').getAttribute('data-id')} />,
    document.getElementById('table')
);

ReactDOM.render(
    <LoadoutSidebar />,
    document.getElementById('sidebar')
);